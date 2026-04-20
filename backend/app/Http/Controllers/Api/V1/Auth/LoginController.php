<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService
    ) {}

    /**
     * Handle user login.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $password = $request->validated('password');
        $throttleKey = 'login:'.Str::lower(trim($email));

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            Log::warning('auth.login.throttled', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Trop de tentatives de connexion. Veuillez réessayer dans une minute.',
                    'code' => 'THROTTLED',
                ],
            ], 429)->header('Retry-After', '60');
        }

        $result = $this->loginService->login(
            $email,
            $password
        );

        if ($result === null) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'error' => [
                    'message' => 'Email ou mot de passe incorrect',
                    'code' => 'AUTH_FAILED',
                ],
            ], 401);
        }

        if (isset($result['error']) && $result['error'] === 'ACCOUNT_DEACTIVATED') {
            return response()->json([
                'error' => [
                    'message' => 'Votre compte a été désactivé',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ],
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            'message' => 'Connexion réussie',
            'meta' => [],
        ], 200);
    }
}
