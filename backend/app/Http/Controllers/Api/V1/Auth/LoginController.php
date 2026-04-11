<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;

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

        if (isset($result['error']) && $result['error'] === 'ACCOUNT_DEACTIVATED') {
            return response()->json([
                'error' => [
                    'message' => 'Votre compte a été désactivé',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ],
            ], 403);
        }

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
