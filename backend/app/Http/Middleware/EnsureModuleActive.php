<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;

/**
 * Gate a route group behind a purchased/entitled module:
 *   Route::...->middleware('module:transfers')
 *
 * Inert until config('modules.enforced') is true, so shipping this changes
 * nothing until entitlements are backfilled and the frontend is ready. Admins
 * always pass. On failure returns 402 with the module key so the SPA can show
 * an "Unlock this module" prompt.
 */
class EnsureModuleActive
{
    public function __construct(private EntitlementService $entitlements) {}

    public function handle(Request $request, Closure $next, string $moduleKey)
    {
        if (!config('modules.enforced')) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // $moduleKey is a role-agnostic FEATURE; the service resolves it to the
        // caller's concrete module key (and passes through if not gated for the role).
        if ($this->entitlements->isEntitledToFeature($user->id, $moduleKey)) {
            return $next($request);
        }

        return response()->json([
            'message'     => 'This feature is a paid module that is not active on your account.',
            'feature'     => $moduleKey,
            'upgrade_url' => '/modules',
        ], 402);
    }
}
