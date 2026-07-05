<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

/**
 * Recompute module entitlements for every account. Run this BEFORE flipping
 * MODULES_ENFORCED=true so existing accounts get their CORE (and any purchased/
 * promo) entitlements materialised — otherwise the gate would lock everyone out.
 *   php artisan entitlements:backfill
 */
class BackfillEntitlements extends Command
{
    protected $signature = 'entitlements:backfill';
    protected $description = 'Recompute module entitlements for every account.';

    public function handle(EntitlementService $entitlements): int
    {
        $count = 0;
        User::query()->select('id')->orderBy('id')->chunk(200, function ($users) use ($entitlements, &$count) {
            foreach ($users as $u) { $entitlements->recompute($u->id); $count++; }
        });
        $this->info("Recomputed entitlements for {$count} account(s).");
        return self::SUCCESS;
    }
}
