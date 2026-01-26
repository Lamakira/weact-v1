<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsFace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum SPA stateful authentication
        // This applies EnsureFrontendRequestsAreStateful middleware to API routes
        // CSRF protection is active for all stateful requests
        $middleware->statefulApi();

        // Register custom role-based middlewares
        $middleware->alias([
            'face' => EnsureUserIsFace::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
