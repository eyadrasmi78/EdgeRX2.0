<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

/**
 * Give EXISTING accounts a free grace window on every module in their role, then
 * they convert to paid when it expires. Run this ONCE just before flipping
 * MODULES_ENFORCED=true. New signups (created after) get no grace date, so they
 * land on the freemium model immediately.
 *   php artisan entitlements:grant-grace --days=60
 */
class GrantModuleGrace extends Command
{
    protected $signature = 'entitlements:grant-grace {--days=60 : length of the grace window} {--all : re-apply even to accounts that already have a grace date}';
    protected $description = 'Grant existing accounts a free module grace window (grace-then-convert).';

    public function handle(EntitlementService $entitlements): int
    {
        $days  = max(1, (int) $this->option('days'));
        $until = now()->addDays($days);

        $q = User::query();
        if (!$this->option('all')) {
            $q->whereNull('module_grace_until'); // don't reset/extend accounts that already have grace
        }
        $ids = $q->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No accounts to grant grace to.');
            return self::SUCCESS;
        }

        User::whereIn('id', $ids)->update(['module_grace_until' => $until]);
        foreach ($ids as $id) {
            $entitlements->recompute($id);
        }

        $this->info("Granted a {$days}-day grace window to {$ids->count()} account(s) — free through {$until->toDateString()}.");
        $this->line('New signups after now get no grace (freemium immediately).');
        return self::SUCCESS;
    }
}
