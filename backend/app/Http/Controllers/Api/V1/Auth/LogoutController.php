<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    /**
     * Handle user logout - revoke current access token.
     *
     * Route is protected by auth:sanctum middleware, so $request->user() is always available.
     * Uses Sanctum's recommended approach: currentAccessToken()->delete()
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Revoke only the current token (allows multi-device sessions)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'data' => null,
            'message' => 'Déconnexion réussie',
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
