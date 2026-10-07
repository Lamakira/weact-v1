<?php

namespace App\Providers;

use App\Models\Notification;
use App\Observers\NotificationObserver;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Block destructive database commands (migrate:fresh, migrate:refresh, db:wipe)
        // everywhere except the testing env — RefreshDatabase needs them on weact_test.
        // Blocks accidental wipes of the dev `weact` database from a stray `artisan migrate:fresh`.
        DB::prohibitDestructiveCommands(
            ! $this->app->environment('testing')
        );

        // Broadcast NotificationCreated event whenever a notification is persisted
        Notification::observe(NotificationObserver::class);

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

        // Rate limiter for authenticated SPA read endpoints.
        // The UI mounts multiple independent sections in parallel, so 60/min is too tight
        // for profile and dashboard pages despite being harmless read traffic.
        RateLimiter::for('ui-read', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
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

        $this->registerSecurityRateLimiters();
    }

    /**
     * Named limiters for the security-relevant endpoints.
     *
     * An inline `throttle:X,Y` keys on user-id/IP only, so every inline limit on
     * the same principal shares ONE counter regardless of route (public browsing
     * ate the register/forgot-password allowance, a `throttle:60,1` request
     * shortened the 10-minute password-change window). A named limiter's key is
     * prefixed by its name, giving each purpose its own bucket.
     */
    private function registerSecurityRateLimiters(): void
    {
        $byIp = fn (Request $request): string => (string) $request->ip();
        // Authenticated user routes (admins are refused there by `api.token`).
        $byUserOrIp = fn (Request $request): string => (string) ($request->user()?->id ?: $request->ip());

        // Coarse per-IP backstop vs password spraying; the per-account limit lives in LoginController.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)->by($byIp($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
        RateLimiter::for('forgot-password', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
        RateLimiter::for('reset-password', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
        RateLimiter::for('email-verification-resend', fn (Request $request) => Limit::perMinute(1)->by($byUserOrIp($request)));
        RateLimiter::for('email-change', fn (Request $request) => Limit::perMinutes(10, 3)->by($byUserOrIp($request)));
        RateLimiter::for('password-change', fn (Request $request) => Limit::perMinutes(10, 5)->by($byUserOrIp($request)));

        // Signed public links (verify email / confirm email change): one bucket per route and IP.
        RateLimiter::for('email-link', fn (Request $request) => Limit::perMinute(10)
            ->by(($request->route()?->getName() ?? 'email-link').'|'.$request->ip()));

        // Admin surface: per-IP backstops; the per-account limit lives in AdminAuthThrottle.
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
        RateLimiter::for('admin-two-factor', fn (Request $request) => Limit::perMinute(10)->by($byIp($request)));
        RateLimiter::for('admin-forgot-password', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
        RateLimiter::for('admin-reset-password', fn (Request $request) => Limit::perMinute(5)->by($byIp($request)));
    }
}
