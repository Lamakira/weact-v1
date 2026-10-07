<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\Admin\AdminTwoFactorLoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Admin\AdminLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AdminLoginService $loginService
    ) {}

    /**
     * Handle admin login (password step).
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->loginService->login(
            $request->validated('email'),
            $request->validated('password'),
            (string) $request->ip(),
        );

        return match ($result['status']) {
            'locked' => $this->lockedResponse($result['retry_after']),
            'invalid' => $this->invalidCredentialsResponse(),
            'two_factor' => response()->json([
                'data' => [
                    'two_factor_required' => true,
                    'challenge' => $result['challenge'],
                ],
                'message' => 'Code de vérification requis',
                'meta' => [],
            ], 200),
            'ok' => $this->tokenResponse($result['admin'], $result['token']),
        };
    }

    /**
     * Handle the second login step (TOTP or recovery code).
     */
    public function twoFactorChallenge(AdminTwoFactorLoginRequest $request): JsonResponse
    {
        $result = $this->loginService->completeTwoFactor(
            $request->validated('challenge'),
            $request->validated('code'),
            $request->validated('recovery_code'),
            (string) $request->ip(),
        );

        return match ($result['status']) {
            'locked' => $this->lockedResponse($result['retry_after']),
            'challenge_invalid' => response()->json([
                'error' => [
                    'message' => 'La session de connexion a expiré. Veuillez vous reconnecter.',
                    'code' => 'TWO_FACTOR_CHALLENGE_INVALID',
                ],
            ], 401),
            'invalid_code' => response()->json([
                'error' => [
                    'message' => 'Code de vérification incorrect',
                    'code' => 'TWO_FACTOR_INVALID_CODE',
                ],
            ], 401),
            'ok' => $this->tokenResponse($result['admin'], $result['token']),
        };
    }

    /**
     * Handle admin logout - revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Return the authenticated admin's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new AdminResource($request->user()),
            'message' => 'Profil admin récupéré avec succès',
        ]);
    }

    private function tokenResponse(Admin $admin, string $token): JsonResponse
    {
        return response()->json([
            'data' => [
                'admin' => new AdminResource($admin),
                'token' => $token,
            ],
            'message' => 'Connexion admin réussie',
            'meta' => [],
        ], 200);
    }

    private function invalidCredentialsResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => 'Email ou mot de passe incorrect',
                'code' => 'AUTH_FAILED',
            ],
        ], 401);
    }

    /**
     * Generic lockout answer: identical whether or not the email exists.
     */
    private function lockedResponse(int $retryAfter): JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => 'Trop de tentatives de connexion. Veuillez réessayer dans '.(int) ceil($retryAfter / 60).' minute(s).',
                'code' => 'THROTTLED',
            ],
        ], 429)->header('Retry-After', (string) $retryAfter);
    }
}
