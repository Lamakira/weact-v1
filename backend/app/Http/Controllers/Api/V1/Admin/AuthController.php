<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminResource;
use App\Services\Admin\AdminLoginService;
use Illuminate\Http\JsonResponse;

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
}
