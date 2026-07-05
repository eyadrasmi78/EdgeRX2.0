<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

/**
 * Mark past-due ACTIVE subscriptions EXPIRED and recompute the affected accounts'
 * entitlements. Runs nightly via the scheduler (see routes/console.php).
 *   php artisan subscriptions:expire
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire past-due subscriptions and recompute entitlements.';

    public function handle(EntitlementService $entitlements): int
    {
        $due = Subscription::where('status', 'ACTIVE')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No subscriptions due to expire.');
            return self::SUCCESS;
        }

        $accounts = $due->pluck('account_id')->unique();
        Subscription::whereIn('id', $due->pluck('id'))->update(['status' => 'EXPIRED']);
        foreach ($accounts as $accountId) {
            $entitlements->recompute($accountId);
        }

        $this->info("Expired {$due->count()} subscription(s) across {$accounts->count()} account(s).");
        return self::SUCCESS;
    }
}
