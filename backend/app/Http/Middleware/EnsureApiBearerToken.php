<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiBearerToken
{
    /**
     * Force API authentication to use the current Bearer token instead of a stale session user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $accessToken = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;

        if ($accessToken === null || $accessToken->tokenable === null) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Unauthenticated',
                    'code' => 'UNAUTHENTICATED',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tokenable = $accessToken->tokenable;

        if (! $tokenable instanceof User && ! $tokenable instanceof Admin) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Unauthenticated',
                    'code' => 'UNAUTHENTICATED',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $authenticatedUser = $tokenable->withAccessToken($accessToken);

        Auth::setUser($authenticatedUser);
        $request->setUserResolver(static fn () => $authenticatedUser);

        return $next($request);
    }
}
