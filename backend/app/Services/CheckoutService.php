<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * checkout.com Payment Links integration for module subscriptions.
 * Docs: https://www.checkout.com/docs — Payment Links API + webhooks.
 */
class CheckoutService
{
    public function isConfigured(): bool
    {
        return !empty(config('checkout.secret'));
    }

    /** Convert a KWD (etc.) decimal amount to checkout.com minor units. */
    public function toMinorUnits(float $amount, string $currency): int
    {
        $decimals = in_array(strtoupper($currency), config('checkout.three_decimal_currencies'), true) ? 3 : 2;
        return (int) round($amount * (10 ** $decimals));
    }

    /**
     * Create a payment link for a pending subscription. Returns the hosted-payment
     * redirect URL, or null on failure.
     */
    public function createPaymentLink(Subscription $sub, User $user, Module $module, float $priceKd): ?string
    {
        $currency = 'KWD';
        $payload = [
            'amount'      => $this->toMinorUnits($priceKd, $currency),
            'currency'    => $currency,
            'reference'   => 'sub_' . $sub->id,
            'description' => $module->name . ' (' . $sub->billing_period . ')',
            'billing'     => ['address' => ['country' => 'KW']],
            'customer'    => ['email' => $user->email, 'name' => $user->name],
            'return_url'  => config('checkout.return_url'),
            'metadata'    => ['subscription_id' => (string) $sub->id, 'account_id' => (string) $user->id],
        ];
        if ($channel = config('checkout.processing_channel')) {
            $payload['processing_channel_id'] = $channel;
        }

        try {
            $res = Http::withToken(config('checkout.secret'))
                ->acceptJson()
                ->timeout(15)
                ->post(config('checkout.base_url') . '/payment-links', $payload);

            if (!$res->successful()) {
                Log::warning('checkout.com payment-link failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }
            return $res->json('_links.redirect.href');
        } catch (\Throwable $e) {
            Log::error('checkout.com payment-link exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /** Verify a webhook's Cko-Signature (HMAC-SHA256 of the raw body with the signing key). */
    public function verifyWebhook(string $rawBody, ?string $signature): bool
    {
        $secret = config('checkout.webhook_secret');
        if (!$secret || !$signature) return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
