<?php

namespace App\Services;

use App\Models\AccountModuleEntitlement;
use App\Models\Module;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the materialised account_module_entitlements rows for an account from
 * three sources: CORE (free, by role), PURCHASED (active subscriptions), and
 * PROMO (valid, unexpired code redemptions). The module gate reads only the
 * materialised table, so the request path is a single indexed lookup.
 */
class EntitlementService
{
    /** Map a user role to the module role_scope(s) that apply to it. */
    public static function scopesForRole(string $role): array
    {
        return match ($role) {
            'CUSTOMER'         => ['CUSTOMER'],
            'PHARMACY_MASTER'  => ['CUSTOMER', 'MASTER'], // master keeps customer core + chain modules
            'SUPPLIER'         => ['SUPPLIER'],
            'FOREIGN_SUPPLIER' => ['FOREIGN'],
            default            => [],                     // ADMIN bypasses the gate entirely
        };
    }

    public function recompute(string $accountId): void
    {
        $user = User::find($accountId);
        if (!$user) return;

        $desired = []; // module_key => ['source'=>..., 'expires_at'=>...]

        // 1. CORE — free modules for the account's role scope(s).
        $scopes = self::scopesForRole($user->role);
        if ($scopes) {
            Module::where('is_core', true)->whereIn('role_scope', $scopes)->pluck('key')
                ->each(function ($key) use (&$desired) {
                    $desired[$key] = ['source' => 'CORE', 'expires_at' => null];
                });
        }

        // 2. PURCHASED — active, in-period subscriptions.
        Subscription::where('account_id', $accountId)
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('current_period_end')->orWhere('current_period_end', '>=', now()))
            ->pluck('module_key')
            ->each(function ($key) use (&$desired) {
                $desired[$key] = ['source' => 'PURCHASED', 'expires_at' => null];
            });

        // 3. PROMO — valid redemptions still within their waiver window.
        $redemptions = PromoCodeRedemption::query()
            ->join('promo_codes', 'promo_codes.id', '=', 'promo_code_redemptions.promo_code_id')
            ->where('promo_code_redemptions.account_id', $accountId)
            ->select('promo_codes.module_keys', 'promo_codes.waiver_days', 'promo_codes.expires_at as code_expires', 'promo_code_redemptions.redeemed_at')
            ->get();
        foreach ($redemptions as $red) {
            if ($red->code_expires && now()->greaterThan($red->code_expires)) continue;
            $expiresAt = $red->waiver_days
                ? \Carbon\Carbon::parse($red->redeemed_at)->addDays((int) $red->waiver_days)
                : null;
            if ($expiresAt && now()->greaterThan($expiresAt)) continue;
            foreach (json_decode($red->module_keys, true) ?? [] as $key) {
                // Don't downgrade a PURCHASED/CORE entitlement to PROMO.
                if (!isset($desired[$key])) {
                    $desired[$key] = ['source' => 'PROMO', 'expires_at' => $expiresAt];
                }
            }
        }

        DB::transaction(function () use ($accountId, $desired) {
            AccountModuleEntitlement::where('account_id', $accountId)
                ->whereNotIn('module_key', array_keys($desired) ?: ['__none__'])
                ->delete();
            foreach ($desired as $key => $meta) {
                AccountModuleEntitlement::updateOrCreate(
                    ['account_id' => $accountId, 'module_key' => $key],
                    ['source' => $meta['source'], 'active' => true,
                     'activated_at' => now(), 'expires_at' => $meta['expires_at']],
                );
            }
        });
    }

    public function isEntitled(string $accountId, string $moduleKey): bool
    {
        return AccountModuleEntitlement::where('account_id', $accountId)
            ->where('module_key', $moduleKey)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->exists();
    }
}
