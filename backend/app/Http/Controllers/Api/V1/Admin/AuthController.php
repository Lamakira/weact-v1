<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminResource;
use App\Services\Admin\AdminLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AdminLoginService $loginService
    ) {}

    /**
     * Handle admin login.
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $result = $this->loginService->login(
            $request->validated('email'),
            $request->validated('password')
        );

        if ($result === null) {
            return response()->json([
                'error' => [
                    'message' => 'Email ou mot de passe incorrect',
                    'code' => 'AUTH_FAILED',
                ],
            ], 401);
        }

        return response()->json([
            'data' => [
                'admin' => new AdminResource($result['admin']),
                'token' => $result['token'],
            ],
            'message' => 'Connexion admin réussie',
            'meta' => [],
        ], 200);
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
}
