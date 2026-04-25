<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Static healthz endpoint pulled out of routes/api.php (N6) so route:cache works.
 * Inline closures block route caching; controller methods don't.
 */
class HealthController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'edgerx-api',
            'time' => now()->toIso8601String(),
        ]);
    }
}
