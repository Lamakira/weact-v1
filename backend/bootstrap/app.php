<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureApiBearerToken;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsFace;
use App\Http\Middleware\EnsureUserIsProducer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']]
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse proxies (ngrok in dev, Nginx/LB in prod)
        // Dev:  TRUSTED_PROXIES=*  in .env
        // Prod: TRUSTED_PROXIES=10.0.0.0/8 (internal LB subnet)
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', null),
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );

        // Enable Sanctum SPA stateful authentication
        // This applies EnsureFrontendRequestsAreStateful middleware to API routes
        // CSRF protection is active for all stateful requests
        $middleware->statefulApi();

        // Register custom role-based middlewares
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.role' => EnsureAdminRole::class,
            'api.token' => EnsureApiBearerToken::class,
            'superadmin' => EnsureSuperAdmin::class,
            'face' => EnsureUserIsFace::class,
            'producer' => EnsureUserIsProducer::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
