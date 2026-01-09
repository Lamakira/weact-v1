<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    /**
     * Handle user logout - revoke current access token.
     */
    public function __invoke(Request $request): JsonResponse
    {
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

        $token->delete();

        return response()->json([
            'data' => null,
            'message' => 'Déconnexion réussie',
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
