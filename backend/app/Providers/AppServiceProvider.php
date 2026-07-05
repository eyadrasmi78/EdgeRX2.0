<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // BE: trustProxies is '*', so $request->ip() can be spoofed via X-Forwarded-For.
        // Key login throttling on email+ip (so rotating the header can't bypass the
        // per-account limit) plus a looser per-ip cap. Used by the /auth/login route.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));
            return [
                Limit::perMinute(10)->by($email . '|' . $request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });
    }
}
