<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\CheckoutService;
use App\Services\EntitlementService;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * checkout.com webhook — public, signature-verified. On a successful payment
     * for a PENDING subscription (reference "sub_<id>"), activate it and recompute
     * entitlements. This is the source of truth for activation.
     */
    public function checkoutWebhook(Request $request, CheckoutService $checkout, EntitlementService $entitlements)
    {
        $raw = $request->getContent();
        if (!$checkout->verifyWebhook($raw, $request->header('Cko-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($raw, true) ?: [];
        $type  = $event['type'] ?? '';
        if (!in_array($type, ['payment_approved', 'payment_captured'], true)) {
            return response()->json(['ok' => true]); // ignore unrelated events
        }

        $reference = $event['data']['reference'] ?? '';
        if (!str_starts_with((string) $reference, 'sub_')) {
            return response()->json(['ok' => true]);
        }

        $sub = Subscription::where('id', (int) substr($reference, 4))->where('status', 'PENDING')->first();
        if (!$sub) {
            return response()->json(['ok' => true]); // already processed / unknown
        }

        $months = ['MONTHLY' => 1, 'QUARTERLY' => 3, 'YEARLY' => 12][$sub->billing_period] ?? 1;
        $sub->update([
            'status'               => 'ACTIVE',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonths($months),
        ]);
        $entitlements->recompute($sub->account_id);

        return response()->json(['ok' => true]);
    }
}
