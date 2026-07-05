<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromoCodesController extends Controller
{
    public function __construct(private EntitlementService $entitlements) {}

    /** GET /api/admin/promo-codes — list codes + redemption status (admin). */
    public function index()
    {
        return PromoCode::withCount('redemptions')->latest()->get()->map(fn (PromoCode $c) => [
            'id'             => $c->id,
            'code'           => $c->code,
            'customerId'     => $c->customer_id,
            'moduleKeys'     => $c->module_keys,
            'waiverDays'     => $c->waiver_days,
            'maxRedemptions' => $c->max_redemptions,
            'redeemedCount'  => $c->redeemed_count,
            'expiresAt'      => optional($c->expires_at)?->toDateString(),
        ]);
    }

    /** POST /api/admin/promo-codes — generate a fee-waiver code (admin). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'     => ['nullable', 'string', 'exists:users,id'],
            'module_keys'     => ['required', 'array', 'min:1', 'max:3'],
            'module_keys.*'   => ['string', 'exists:modules,key'],
            'waiver_days'     => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_at'      => ['nullable', 'date', 'after:now'],
        ]);

        // Unique human-ish code.
        do { $code = 'EDGE-' . strtoupper(Str::random(8)); } while (PromoCode::where('code', $code)->exists());

        $promo = PromoCode::create([
            'code'            => $code,
            'customer_id'     => $data['customer_id'] ?? null,
            'module_keys'     => $data['module_keys'],
            'waiver_days'     => $data['waiver_days'] ?? null,
            'max_redemptions' => $data['max_redemptions'] ?? 1,
            'redeemed_count'  => 0,
            'expires_at'      => $data['expires_at'] ?? null,
            'created_by'      => $request->user()->id,
        ]);

        return response()->json(['code' => $promo->code, 'moduleKeys' => $promo->module_keys], 201);
    }

    /** POST /api/promo-codes/redeem {code} — customer redeems, waived modules activate. */
    public function redeem(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string']]);

        $promo = PromoCode::where('code', strtoupper(trim($data['code'])))->first();
        if (!$promo || !$promo->isRedeemable()) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }
        if ($promo->customer_id && $promo->customer_id !== $user->id) {
            return response()->json(['message' => 'This code is not valid for your account.'], 403);
        }
        if (PromoCodeRedemption::where('promo_code_id', $promo->id)->where('account_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already redeemed this code.'], 422);
        }
        // Only waive modules that apply to this account's role.
        $scopes  = EntitlementService::scopesForRole($user->role);
        $valid   = Module::whereIn('key', $promo->module_keys)->whereIn('role_scope', $scopes)->pluck('key');
        if ($valid->isEmpty()) {
            return response()->json(['message' => 'This code has no modules that apply to your account.'], 422);
        }

        PromoCodeRedemption::create([
            'promo_code_id' => $promo->id,
            'account_id'    => $user->id,
            'redeemed_at'   => now(),
        ]);
        $promo->increment('redeemed_count');

        $this->entitlements->recompute($user->id);

        return response()->json(['message' => 'Code redeemed.', 'activated' => $valid->values()]);
    }
}
