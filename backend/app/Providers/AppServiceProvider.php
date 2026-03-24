<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Set Carbon locale globally for French date formatting
        Carbon::setLocale('fr');

        // Rate limiter for upload routes — keyed by authenticated user ID, not IP
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for polling routes — generous limit keyed by user ID to avoid false throttling
        RateLimiter::for('polling', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for withdrawal — 5 attempts per 10 minutes per user
        RateLimiter::for('withdrawals', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Trop de tentatives de retrait. Veuillez réessayer dans quelques minutes.',
                    ], 429);
                });
        });
    }
}
