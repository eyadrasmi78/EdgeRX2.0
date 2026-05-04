<?php

namespace App\Services;

use App\Models\BuyingGroup;
use App\Models\ChatRoom;
use App\Models\Order;
use App\Models\OrderHistoryLog;
use App\Models\User;
use App\Notifications\EdgeRxNotification;
use App\Notifications\Recipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encapsulates the "release" transition: BuyingGroup goes from
 * (OPEN|COLLECTING|LOCKED) → RELEASED. Creates one Order per ACCEPTED
 * member, apportions the bonus, links each Order back to the group,
 * fires notifications.
 *
 * If the threshold is NOT met → the group is DISSOLVED instead. No orders.
 *
 * Per locked decision #15: supplier-side view = N independent orders linked
 * by group_id (the "GROUP: …" badge is purely a frontend render hint).
 */
final class BuyingGroupReleaseService
{
    public function __construct(private BuyingGroupBonusApportionment $apportion) {}

    /**
     * @return array{released: bool, orderIds: string[], reason?: string}
     */
    public function release(BuyingGroup $group): array
    {
        // BE-2 fix: lock the group row for the duration of the release transaction
        // so a concurrent admin-release + cron-fire (or two parallel accept→auto-release
        // paths) cannot produce duplicate child orders. We re-fetch under SELECT FOR UPDATE
        // and re-check terminal state before any writes.
        $orderIds = [];
        $result = DB::transaction(function () use ($group, &$orderIds) {
            $locked = BuyingGroup::where('id', $group->id)->lockForUpdate()->first();
            if (!$locked) {
                return ['released' => false, 'orderIds' => [], 'reason' => 'group_missing'];
            }
            if ($locked->isTerminal()) {
                return ['released' => false, 'orderIds' => [], 'reason' => 'already_terminal'];
            }

            $accepted = $locked->members()->where('status', 'ACCEPTED')->get();
            if ($accepted->isEmpty()) {
                $this->dissolveLocked($locked, 'no_accepted_members');
                return ['released' => false, 'orderIds' => [], 'reason' => 'no_accepted_members'];
            }
            if ($locked->acceptedQuantity() < (int) $locked->target_quantity) {
                $this->dissolveLocked($locked, 'threshold_not_met');
                return ['released' => false, 'orderIds' => [], 'reason' => 'threshold_not_met'];
            }

            $product = $locked->product;
            if (!$product) {
                $this->dissolveLocked($locked, 'product_missing');
                return ['released' => false, 'orderIds' => [], 'reason' => 'product_missing'];
            }

            $shares = $this->apportion->compute($locked);
            $this->createOrdersFor($locked, $accepted, $product, $shares, $orderIds);
            return ['released' => true, 'orderIds' => $orderIds];
        });

        if (($result['released'] ?? false) === true) {
            $accepted = $group->fresh()->members()->where('status', 'ACCEPTED')->get();
            $this->notifyOnRelease($group->fresh(), $accepted->pluck('customer_id')->all(), $orderIds);
        } elseif (in_array($result['reason'] ?? null, ['no_accepted_members', 'threshold_not_met', 'product_missing'], true)) {
            $this->notifyOnDissolve($group->fresh(), $result['reason']);
        }
        return $result;
    }

    /** Internal: create the N child orders inside the locked transaction. */
    private function createOrdersFor(BuyingGroup $group, $accepted, $product, array $shares, array &$orderIds): void
    {
        DB::transaction(function () use ($group, $accepted, $product, $shares, &$orderIds) {
            foreach ($accepted as $m) {
                $bonusQty = $shares[$m->id] ?? null;
                $order = Order::create([
                    'id' => (string) Str::uuid(),
                    'order_number' => 'ORD-' . now()->format('Y') . '-BG' . strtoupper(Str::random(5)),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'customer_id' => $m->customer_id,
                    'customer_name' => $m->customer?->name ?? '',
                    'supplier_id' => $product->supplier_id,
                    'supplier_name' => $product->supplier_name,
                    'placed_by_user_id' => null,
                    'buying_group_id' => $group->id,
                    'quantity' => (int) $m->committed_quantity,
                    'bonus_quantity' => $bonusQty,
                    'unit_of_measurement' => $product->unit_of_measurement,
                    'status' => 'Received',
                    'date' => now(),
                ]);
                OrderHistoryLog::create([
                    'order_id' => $order->id,
                    'status' => 'Received',
                    'timestamp' => now(),
                    'note' => "Released from buying group: {$group->name}",
                ]);
                ChatRoom::firstOrCreate(['order_id' => $order->id]);

                $m->update([
                    'apportioned_bonus' => $bonusQty,
                    'resulting_order_id' => $order->id,
                ]);

                $orderIds[] = $order->id;
            }

            $group->update([
                'status' => 'RELEASED',
                'released_at' => now(),
            ]);
        });
    }

    /** Mark group as DISSOLVED + notify everyone with a reason. (admin path — locks group row) */
    public function dissolve(BuyingGroup $group, string $reason = 'admin_cancel'): array
    {
        $result = DB::transaction(function () use ($group, $reason) {
            $locked = BuyingGroup::where('id', $group->id)->lockForUpdate()->first();
            if (!$locked || $locked->isTerminal()) {
                return ['released' => false, 'orderIds' => [], 'reason' => 'already_terminal'];
            }
            $this->dissolveLocked($locked, $reason);
            return ['released' => false, 'orderIds' => [], 'reason' => $reason];
        });

        if (($result['reason'] ?? null) === $reason) {
            $this->notifyOnDissolve($group->fresh(), $reason);
        }
        return $result;
    }

    /** Internal: flip status to DISSOLVED inside an already-locked transaction. */
    private function dissolveLocked(BuyingGroup $group, string $reason): void
    {
        $group->update([
            'status' => 'DISSOLVED',
            'dissolved_at' => now(),
        ]);
    }

    /** Single supplier notification + per-member notification (with bonus). */
    private function notifyOnRelease(BuyingGroup $group, array $memberCustomerIds, array $orderIds): void
    {
        $supplier = User::find($group->supplier_id);
        if ($supplier) {
            $supplier->notify(new EdgeRxNotification(
                kind: 'buying_group_released',
                title: 'Buying group released',
                message: "Group \"{$group->name}\" released " . count($orderIds) . " orders for " . $group->acceptedQuantity() . " × {$group->product?->name}.",
                actionUrl: rtrim(config('app.frontend_url'), '/') . '/',
                data: ['groupId' => $group->id, 'orderIds' => $orderIds],
            ));
        }
        foreach ($memberCustomerIds as $cid) {
            $customer = User::with('masteredBy')->find($cid);
            if (!$customer) continue;
            Recipients::notify($customer, new EdgeRxNotification(
                kind: 'buying_group_released',
                title: 'Your buying group order is in!',
                message: "Group \"{$group->name}\" reached its target. Your portion has been placed with {$group->supplier?->name}.",
                actionUrl: rtrim(config('app.frontend_url'), '/') . '/',
                data: ['groupId' => $group->id],
            ));
        }
    }

    private function notifyOnDissolve(BuyingGroup $group, string $reason): void
    {
        $msg = match ($reason) {
            'threshold_not_met' => 'Did not reach the bulk threshold — dissolved.',
            'no_accepted_members' => 'No members accepted — dissolved.',
            'admin_cancel' => 'Cancelled by admin.',
            'all_members_declined' => 'All members declined — dissolved.',
            'product_missing' => 'Product no longer available — dissolved.',
            default => 'Dissolved.',
        };
        // BE-34 fix: eager-load members + their customer + masteredBy in one
        // shot rather than firing N+1 SELECTs (one per member, plus one per
        // master fan-out). For a 20-member group this drops 41 queries to 3.
        $group->loadMissing(['members.customer.masteredBy']);
        foreach ($group->members as $m) {
            $customer = $m->customer;
            if (!$customer) continue;
            Recipients::notify($customer, new EdgeRxNotification(
                kind: 'buying_group_dissolved',
                title: "Buying group dissolved: {$group->name}",
                message: $msg,
                actionUrl: rtrim(config('app.frontend_url'), '/') . '/',
                data: ['groupId' => $group->id, 'reason' => $reason],
            ));
        }
    }
}
