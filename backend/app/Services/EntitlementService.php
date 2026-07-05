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
    /**
     * A shared route (e.g. /buying-groups) is gated by a role-agnostic FEATURE.
     * This maps (role, feature) to the concrete module key that role must own.
     * A feature not listed for a role is NOT gated for it (pass-through).
     */
    private const FEATURE_MAP = [
        'CUSTOMER' => [
            'buying_groups'      => 'buying_groups',
            'pricing_agreements' => 'pricing_agreements',
            'transfers'          => 'transfers',
            'ai_analytics'       => 'ai_analytics',
            'order_chat'         => 'order_chat',
            'market_feed'        => 'market_feed',
        ],
        'PHARMACY_MASTER' => [
            'buying_groups'      => 'master_buying_groups',
            'pricing_agreements' => 'master_agreements',
            'transfers'          => 'master_transfers',
            'ai_analytics'       => 'master_ai_analytics',
            'chain_management'   => 'chain_management',
            'order_chat'         => 'order_chat',
            'market_feed'        => 'market_feed',
        ],
        'SUPPLIER' => [
            'pricing_agreements'   => 'supplier_agreements',
            'buying_groups'        => 'supplier_buying_groups',
            'transfer_qc'          => 'transfer_qc',
            'foreign_partnerships' => 'foreign_partnerships',
            'ai_analytics'         => 'supplier_ai_analytics',
        ],
        'FOREIGN_SUPPLIER' => [
            'foreign_plan' => 'foreign_plan',
        ],
    ];

    public static function moduleKeyForFeature(string $role, string $feature): ?string
    {
        return self::FEATURE_MAP[$role][$feature] ?? null;
    }

    /** Reverse of moduleKeyForFeature: the feature a role's module key gates. */
    public static function featureForModuleKey(string $role, string $key): ?string
    {
        $feature = array_search($key, self::FEATURE_MAP[$role] ?? [], true);
        return $feature === false ? null : $feature;
    }

    /** Feature-level gate check: resolve the role's module key, then entitlement. */
    public function isEntitledToFeature(string $accountId, string $feature): bool
    {
        $user = User::find($accountId);
        if (!$user) return false;
        $key = self::moduleKeyForFeature($user->role, $feature);
        if (!$key) return true; // feature isn't a gated module for this role
        return $this->isEntitled($accountId, $key);
    }

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
