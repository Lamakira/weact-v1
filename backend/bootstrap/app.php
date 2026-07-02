<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureApiBearerToken;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsFace;
use App\Http\Middleware\EnsureUserIsFaceOrProducer;
use App\Http\Middleware\EnsureUserIsProducer;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // Force Laravel locale to 'fr' on every API request (FIX-22.1)
        $middleware->appendToGroup('api', SetLocale::class);

        // Register custom role-based middlewares
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.role' => EnsureAdminRole::class,
            'api.token' => EnsureApiBearerToken::class,
            'superadmin' => EnsureSuperAdmin::class,
            'face' => EnsureUserIsFace::class,
            'face_or_producer' => EnsureUserIsFaceOrProducer::class,
            'producer' => EnsureUserIsProducer::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // FIX-22.2 — Global API exception normalization.
        //
        // Every uncaught exception raised by a JSON / API request is rendered
        // with the standardized envelope { error: { message, code } }
        // (ValidationException also keeps the legacy top-level `errors` /
        // `message` for the 176 assertJsonValidationErrors non-regression —
        // see AC #9).
        //
        // Coverage: the predicate below gates normalization to
        // `$request->expectsJson()` OR paths starting with `api/`. That
        // includes /api/v1/webhooks/fedapay — intentional, so webhook
        // callers get a coherent JSON error body if anything escapes the
        // controller (see AC #8 note / AC #12).
        //
        // Out of scope here: frontend consumption is centralized later via
        // FIX-22.3 (errorFormatter helper + composable refactor). Legacy
        // lower-snake codes in the ErrorCodes enum are deliberately NOT
        // renamed in this ticket.
        $shouldNormalize = fn (Request $request): bool => $request->expectsJson()
            || $request->path() === 'api'
            || str_starts_with($request->path(), 'api/');

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            // OWASP A09 (M-3): low-noise trace of unauthenticated access to protected endpoints.
            Log::info('auth.unauthenticated', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => ['message' => 'Non authentifié.', 'code' => 'UNAUTHENTICATED'],
            ], 401);
        });

        // Note: AuthorizationException is intentionally NOT handled here.
        // Laravel's Handler::prepareException() converts every
        // AuthorizationException (without explicit status) into an
        // AccessDeniedHttpException (subclass of HttpException) before
        // renderViaCallbacks() runs, so the HttpException closure below
        // (status 403 → FORBIDDEN) already covers it.

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            return response()->json([
                'error' => ['message' => 'Ressource introuvable.', 'code' => 'NOT_FOUND'],
            ], 404);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            return response()->json([
                'error' => ['message' => 'Ressource introuvable.', 'code' => 'NOT_FOUND'],
            ], 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            $errors = $e->errors();
            $message = 'Les données fournies ne sont pas valides';

            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $message,
                    'details' => $errors,
                ],
                // Legacy top-level fields preserved for assertJsonValidationErrors
                // non-regression (AC #9).
                'errors' => $errors,
                'message' => $message,
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            $response = response()->json([
                'error' => ['message' => 'Trop de requêtes. Veuillez patienter.', 'code' => 'THROTTLED'],
            ], 429);

            $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
            if ($retryAfter !== null) {
                $response->header('Retry-After', (string) $retryAfter);
            }

            return $response;
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            $statusMap = [
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                409 => 'CONFLICT',
                410 => 'GONE',
                422 => 'UNPROCESSABLE',
                429 => 'THROTTLED',
            ];
            $defaults = [
                400 => 'Requête invalide.',
                401 => 'Non authentifié.',
                403 => 'Action non autorisée.',
                404 => 'Ressource introuvable.',
                405 => 'Méthode non autorisée.',
                409 => 'Conflit.',
                410 => 'Ressource supprimée.',
                422 => 'Requête non traitable.',
                429 => 'Trop de requêtes. Veuillez patienter.',
            ];

            $status = $e->getStatusCode();

            // OWASP A09 (M-3): an authenticated principal hitting a forbidden resource is a
            // meaningful signal (probing / compromised account) — log 403s for monitoring.
            if ($status === 403) {
                Log::warning('auth.forbidden', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => optional($request->user())->id,
                ]);
            }

            $code = $statusMap[$status] ?? 'HTTP_ERROR';
            $message = $e->getMessage() !== '' ? $e->getMessage() : ($defaults[$status] ?? 'Erreur HTTP.');

            return response()->json([
                'error' => ['message' => $message, 'code' => $code],
            ], $status);
        });

        // Throwable is registered LAST per Laravel convention — specifics
        // already match by `instanceof` regardless of order, but keeping
        // the fallback last mirrors the framework documentation.
        $exceptions->render(function (\Throwable $e, Request $request) use ($shouldNormalize) {
            if (! $shouldNormalize($request)) {
                return null;
            }

            // In debug mode let Laravel surface the usual stack trace so
            // developers are not blinded by a generic 500.
            if (config('app.debug')) {
                return null;
            }

            Log::error('api.unhandled_exception', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'path' => $request->path(),
                'user_id' => optional($request->user())->id,
            ]);

            return response()->json([
                'error' => ['message' => 'Erreur interne du serveur.', 'code' => 'INTERNAL_ERROR'],
            ], 500);
        });
    })->create();
