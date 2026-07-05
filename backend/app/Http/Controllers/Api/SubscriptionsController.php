<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Subscription;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionsController extends Controller
{
    public function __construct(private EntitlementService $entitlements) {}

    /** GET /api/modules — catalogue for the caller's role + live entitlement state. */
    public function index(Request $request)
    {
        $user   = $request->user();
        $scopes = EntitlementService::scopesForRole($user->role);
        if (!$scopes) return response()->json(['modules' => []]);

        $modules = Module::whereIn('role_scope', $scopes)->orderBy('sort_order')->get();
        $subs = Subscription::where('account_id', $user->id)->where('status', 'ACTIVE')
            ->get()->keyBy('module_key');

        return response()->json([
            'enforced' => (bool) config('modules.enforced'),
            'modules'  => $modules->map(function (Module $m) use ($user, $subs) {
                return [
                    'key'            => $m->key,
                    'feature'        => EntitlementService::featureForModuleKey($user->role, $m->key),
                    'name'           => $m->name,
                    'roleScope'      => $m->role_scope,
                    'monthlyPriceKd' => (float) $m->monthly_price_kd,
                    'isCore'         => $m->is_core,
                    'active'         => $this->entitlements->isEntitled($user->id, $m->key),
                    'billingPeriod'  => $subs[$m->key]->billing_period ?? null,
                    'renewsOn'       => optional($subs[$m->key]->current_period_end ?? null)?->toDateString(),
                ];
            })->values(),
        ]);
    }

    /** POST /api/subscriptions {module_key, billing_period} — purchase/activate a module. */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'module_key'     => ['required', 'string', 'exists:modules,key'],
            'billing_period' => ['required', Rule::in(['MONTHLY', 'QUARTERLY', 'YEARLY'])],
        ]);

        $module = Module::findOrFail($data['module_key']);

        if ($module->is_core && (float) $module->monthly_price_kd == 0.0) {
            return response()->json(['message' => 'This module is free and already included.'], 422);
        }
        if (!in_array($module->role_scope, EntitlementService::scopesForRole($user->role), true)) {
            return response()->json(['message' => 'This module is not available for your account type.'], 403);
        }
        $exists = Subscription::where('account_id', $user->id)
            ->where('module_key', $module->key)->where('status', 'ACTIVE')->exists();
        if ($exists) {
            return response()->json(['message' => 'This module is already active on your account.'], 422);
        }

        [$price, $periodMonths] = $this->priceFor($module, $data['billing_period']);
        $checkout = app(\App\Services\CheckoutService::class);
        $payFirst = $checkout->isConfigured();

        $sub = Subscription::create([
            'account_id'           => $user->id,
            'module_key'           => $module->key,
            'billing_period'       => $data['billing_period'],
            // With checkout.com configured, the subscription stays PENDING until the
            // payment webhook activates it. Without it, activate immediately.
            'status'               => $payFirst ? 'PENDING' : 'ACTIVE',
            'unit_price_kd'        => $price,
            'current_period_start' => $payFirst ? null : now(),
            'current_period_end'   => $payFirst ? null : now()->addMonths($periodMonths),
            'auto_renew'           => true,
        ]);

        if ($payFirst) {
            $redirect = $checkout->createPaymentLink($sub, $user, $module, $price);
            if (!$redirect) {
                $sub->delete();
                return response()->json(['message' => 'Payment could not be started. Please try again.'], 502);
            }
            return response()->json(['requiresPayment' => true, 'redirectUrl' => $redirect, 'priceKd' => $price], 201);
        }

        $this->entitlements->recompute($user->id);
        return response()->json(['message' => 'Module activated.', 'module' => $module->key, 'priceKd' => $price], 201);
    }

    /** POST /api/subscriptions/{id}/cancel — stop auto-renew and end entitlement. */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $sub  = Subscription::where('id', $id)->where('account_id', $user->id)->firstOrFail();
        $sub->update(['status' => 'CANCELLED', 'auto_renew' => false]);
        $this->entitlements->recompute($user->id);
        return response()->json(['message' => 'Subscription cancelled.']);
    }

    /** Per-purchase price and subscription length from the billing model. */
    private function priceFor(Module $module, string $period): array
    {
        $foreign = $module->role_scope === 'FOREIGN';
        $eff = config($foreign ? 'modules.billing_effective_months_foreign' : 'modules.billing_effective_months');
        $periodsPerYear = ['MONTHLY' => 12, 'QUARTERLY' => 4, 'YEARLY' => 1][$period];
        $periodMonths   = ['MONTHLY' => 1,  'QUARTERLY' => 3, 'YEARLY' => 12][$period];
        $multiplier = $eff[$period] / $periodsPerYear;              // e.g. quarterly = 10/4 = 2.5
        $price = round(((float) $module->monthly_price_kd) * $multiplier, 2);
        return [$price, $periodMonths];
    }
}
