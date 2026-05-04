<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyingGroupResource;
use App\Models\BuyingGroup;
use App\Models\BuyingGroupMember;
use App\Models\Product;
use App\Models\User;
use App\Notifications\EdgeRxNotification;
use App\Notifications\Recipients;
use App\Services\BuyingGroupReleaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuyingGroupsController extends Controller
{
    public function __construct(private BuyingGroupReleaseService $release) {}

    /**
     * GET /api/buying-groups
     * - Admin: every group.
     * - Customer: groups they are a member of (any status).
     * - Pharmacy Master: groups any of their child pharmacies are members of (read-only — masters can't act on a buying group, see decision #16).
     * - Supplier: groups where they are the supplier_id.
     * - Everyone else: empty.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = BuyingGroup::with(['product', 'supplier', 'members.customer']);

        // BE-21 fix: use whereHas on the members relation instead of a separate
        // pluck-then-whereIn query. One round trip instead of two. Both customer
        // and pharmacy-master scoping use the same join — just different
        // customer_id sets.
        if ($user->isAdmin()) {
            // no scope
        } elseif ($user->isCustomer()) {
            $query->whereHas('members', fn ($q) => $q->where('customer_id', $user->id));
        } elseif ($user->isPharmacyMaster()) {
            $childIds = $user->masterOf()->pluck('users.id');
            $query->whereHas('members', fn ($q) => $q->whereIn('customer_id', $childIds));
        } elseif ($user->isSupplier()) {
            $query->where('supplier_id', $user->id);
        } else {
            return BuyingGroupResource::collection(collect());
        }

        return BuyingGroupResource::collection($query->orderByDesc('created_at')->get());
    }

    public function show(Request $request, $id)
    {
        $group = BuyingGroup::with(['product', 'supplier', 'members.customer'])->findOrFail($id);
        $this->authorizeView($request, $group);
        return new BuyingGroupResource($group);
    }

    /**
     * POST /api/admin/buying-groups
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin()) abort(403, 'Forbidden.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'productId' => 'required|string|exists:products,id',
            'targetQuantity' => 'required|integer|min:1|max:1000000',
            'windowEndsAt' => 'nullable|date|after:now',
            'memberCustomerIds' => 'nullable|array',
            'memberCustomerIds.*' => 'string|exists:users,id',
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($data['productId']);
        if (!$product->supplier_id) {
            abort(422, 'Product must have a supplier to create a buying group.');
        }

        // Filter member IDs: must be CUSTOMER + APPROVED + NOT linked to any pharmacy master.
        $invitedIds = collect($data['memberCustomerIds'] ?? [])->unique()->values();
        $eligibleIds = $this->filterEligibleCustomers($invitedIds);

        $group = null;
        DB::transaction(function () use ($data, $product, $eligibleIds, $user, &$group) {
            $group = BuyingGroup::create([
                'id' => (string) Str::uuid(),
                'name' => $data['name'],
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'target_quantity' => $data['targetQuantity'],
                'window_ends_at' => $data['windowEndsAt'] ?? null,
                'status' => 'COLLECTING',
                'created_by_admin_id' => $user->id,
            ]);
            foreach ($eligibleIds as $cid) {
                BuyingGroupMember::create([
                    'buying_group_id' => $group->id,
                    'customer_id' => $cid,
                    'status' => 'INVITED',
                ]);
            }
        });

        $this->notifyInvitedMembers($group, $eligibleIds->all());

        return new BuyingGroupResource($group->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * POST /api/admin/buying-groups/{id}/members
     */
    public function addMember(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Forbidden.');
        $group = BuyingGroup::findOrFail($id);
        if ($group->isTerminal()) abort(422, 'Group is already terminal.');

        $data = $request->validate(['customerId' => 'required|string|exists:users,id']);
        $eligible = $this->filterEligibleCustomers(collect([$data['customerId']]));
        if ($eligible->isEmpty()) {
            abort(422, 'Customer not eligible (must be CUSTOMER + APPROVED + not under a pharmacy master).');
        }
        if ($group->members()->where('customer_id', $data['customerId'])->exists()) {
            abort(422, 'Customer is already a member.');
        }
        BuyingGroupMember::create([
            'buying_group_id' => $group->id,
            'customer_id' => $data['customerId'],
            'status' => 'INVITED',
        ]);
        $this->notifyInvitedMembers($group, [$data['customerId']]);
        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * DELETE /api/admin/buying-groups/{id}/members/{memberId}
     */
    public function removeMember(Request $request, $id, $memberId)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Forbidden.');
        $group = BuyingGroup::findOrFail($id);
        if ($group->isTerminal()) abort(422, 'Group is already terminal.');
        $m = $group->members()->where('id', $memberId)->firstOrFail();
        if ($m->isAccepted()) {
            abort(422, 'Cannot remove a member who has already accepted.');
        }
        $m->delete();
        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * POST /api/buying-groups/{id}/commit
     * Member commits a quantity (transitions INVITED → COMMITTED, or revises an existing COMMITTED qty).
     */
    public function commit(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isCustomer()) abort(403, 'Only customers can commit.');
        $group = BuyingGroup::findOrFail($id);
        if (!$group->isOpen()) abort(422, 'Group is no longer accepting commitments.');

        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:1000000',
        ]);

        $m = $group->members()->where('customer_id', $user->id)->firstOrFail();
        if ($m->isDeclined() || $m->isAccepted()) {
            abort(422, 'Cannot revise commitment in current status.');
        }
        $m->update([
            'committed_quantity' => $data['quantity'],
            'status' => 'COMMITTED',
        ]);
        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * POST /api/buying-groups/{id}/accept
     * Member locks their commitment (COMMITTED → ACCEPTED). Triggers auto-release if all accepted + threshold met.
     */
    public function accept(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isCustomer()) abort(403, 'Only customers can accept.');
        $group = BuyingGroup::findOrFail($id);
        if (!$group->isOpen()) abort(422, 'Group is no longer accepting commitments.');

        $m = $group->members()->where('customer_id', $user->id)->firstOrFail();
        if (!$m->isCommitted()) abort(422, 'Must commit a quantity first.');

        $m->update(['status' => 'ACCEPTED']);

        // Auto-release if (a) all non-DECLINED members have ACCEPTED AND (b) threshold met.
        $group->refresh();
        if ($group->allMembersAccepted() && $group->thresholdMet()) {
            $this->release->release($group);
        }

        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * POST /api/buying-groups/{id}/decline
     * Member opts out (any pre-release state → DECLINED).
     */
    public function decline(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isCustomer()) abort(403, 'Only customers can decline.');
        $group = BuyingGroup::findOrFail($id);
        if ($group->isTerminal()) abort(422, 'Group is already terminal.');

        $m = $group->members()->where('customer_id', $user->id)->firstOrFail();
        $m->update(['status' => 'DECLINED']);

        // C-3 fix: handle both branches after a decline:
        //   1. Remaining members all accepted + threshold met → release
        //   2. No accepted members left, OR threshold can't be met regardless
        //      of pending COMMITTED → dissolve
        $group->refresh();
        $remainingActive = $group->members()->whereIn('status', ['INVITED', 'COMMITTED', 'ACCEPTED'])->count();
        $accepted = $group->members()->where('status', 'ACCEPTED')->exists();

        if ($remainingActive === 0 && !$accepted) {
            $this->release->dissolve($group, 'all_members_declined');
        } elseif ($group->allMembersAccepted() && $group->thresholdMet()) {
            $this->release->release($group);
        }

        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /**
     * POST /api/admin/buying-groups/{id}/release  (admin-forced release)
     */
    public function adminRelease(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Forbidden.');
        $group = BuyingGroup::with(['product', 'members.customer'])->findOrFail($id);
        $result = $this->release->release($group);
        return [
            'success' => $result['released'],
            'reason' => $result['reason'] ?? null,
            'orderIds' => $result['orderIds'],
            'group' => new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer'])),
        ];
    }

    /**
     * POST /api/admin/buying-groups/{id}/dissolve
     */
    public function adminDissolve(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) abort(403, 'Forbidden.');
        $group = BuyingGroup::with('members')->findOrFail($id);
        $this->release->dissolve($group, 'admin_cancel');
        return new BuyingGroupResource($group->fresh()->load(['product', 'supplier', 'members.customer']));
    }

    /* ────────────────────────── helpers ────────────────────────── */

    private function authorizeView(Request $request, BuyingGroup $group): void
    {
        $user = $request->user();
        if ($user->isAdmin()) return;

        if ($user->isCustomer() && $group->members()->where('customer_id', $user->id)->exists()) return;

        if ($user->isPharmacyMaster()) {
            $childIds = $user->masterOf()->pluck('users.id');
            if ($group->members()->whereIn('customer_id', $childIds)->exists()) return;
        }

        if ($user->isSupplier() && $group->supplier_id === $user->id) return;

        abort(403, 'Forbidden.');
    }

    /**
     * Filter to: CUSTOMER role + APPROVED status + NOT linked to any pharmacy master.
     * Returns the eligible subset (preserves order).
     */
    private function filterEligibleCustomers(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        if ($ids->isEmpty()) return collect();

        // Customers eligible by role + status
        $eligible = User::whereIn('id', $ids)
            ->where('role', 'CUSTOMER')
            ->where('status', 'APPROVED')
            ->pluck('id');

        // Strip any that are members of a pharmacy master group
        $linked = DB::table('pharmacy_group_members')
            ->whereIn('pharmacy_user_id', $eligible)
            ->pluck('pharmacy_user_id');

        return $eligible->reject(fn ($id) => $linked->contains($id))->values();
    }

    private function notifyInvitedMembers(BuyingGroup $group, array $customerIds): void
    {
        $product = $group->product;
        foreach ($customerIds as $cid) {
            $customer = User::with('masteredBy')->find($cid);
            if (!$customer) continue;
            Recipients::notify($customer, new EdgeRxNotification(
                kind: 'buying_group_invited',
                title: 'You are invited to a buying group',
                message: "Join \"{$group->name}\" to pool a bulk order on {$product?->name} and unlock the group discount.",
                actionUrl: rtrim(config('app.frontend_url'), '/') . '/',
                data: ['groupId' => $group->id, 'productId' => $group->product_id],
            ));
        }
    }
}
