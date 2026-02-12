<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterFaceController;
use App\Http\Controllers\Api\V1\Auth\RegisterProducerController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::prefix('v1')->group(function (): void {
    // Health check endpoint
    Route::get('/health', fn() => response()->json([
        'data' => ['status' => 'ok'],
        'meta' => ['timestamp' => now()->toIso8601String()],
        'message' => 'API is running'
    ]));

    // Authentication routes (public)
    Route::prefix('auth')->group(function (): void {
        Route::post('/register/face', RegisterFaceController::class)
            ->middleware('throttle:5,1')
            ->name('auth.register.face');

        Route::post('/register/producer', RegisterProducerController::class)
            ->middleware('throttle:5,1')
            ->name('auth.register.producer');

        Route::post('/login', LoginController::class)
            ->middleware('throttle:5,1')
            ->name('auth.login');

        Route::post('/forgot-password', ForgotPasswordController::class)
            ->middleware('throttle:5,1')
            ->name('auth.forgot-password');

        Route::post('/reset-password', ResetPasswordController::class)
            ->name('auth.reset-password');

        // Email verification (public - signature validation handled in controller)
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify');
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function (): void {
        // Note: Explicit token validation is required because Sanctum can use
        // session-based auth which may cache the user across requests.
        // This ensures token validity is checked on each request.
        Route::get('/user', function (Request $request) {
            $plainTextToken = $request->bearerToken();
            $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;

            if ($token === null) {
                return response()->json([
                    'error' => [
                        'message' => 'Unauthenticated',
                        'code' => 'UNAUTHENTICATED',
                    ],
                ], Response::HTTP_UNAUTHORIZED);
            }

            return (new UserResource($request->user()))->additional([
                'meta' => [],
                'message' => 'Authenticated user retrieved',
            ]);
        });

        // Protected auth routes
        Route::prefix('auth')->group(function (): void {
            Route::post('/logout', LogoutController::class)
                ->name('auth.logout');
        });

        // Email verification routes (authenticated)
        Route::prefix('email')->group(function (): void {
            Route::post('/verification-notification', [EmailVerificationController::class, 'sendVerificationNotification'])
                ->middleware('throttle:1,1')
                ->name('verification.send');

            Route::get('/verification-status', [EmailVerificationController::class, 'status'])
                ->name('verification.status');
        });
    });
});

// Include modular route files
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/face.php';
require __DIR__ . '/api/producer.php';
require __DIR__ . '/api/public.php';
