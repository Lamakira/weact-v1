<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\PersonalAccessToken as AppPersonalAccessToken;
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
     *
     * Admins and users share numeric ids, so the token's principal type is
     * pinned per surface: `api.token` (default) accepts User tokens only,
     * `api.token:admin` accepts Admin tokens (User tokens are then refused by
     * the `admin` middleware).
     */
    public function handle(Request $request, Closure $next, string $surface = 'user'): Response
    {
        $plainTextToken = $request->bearerToken();
        $accessToken = $plainTextToken ? $this->resolveAccessToken($request, $plainTextToken) : null;

        if ($accessToken === null || $accessToken->tokenable === null || $this->isExpired($accessToken)) {
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

        if ($surface !== 'admin' && $tokenable instanceof Admin) {
            abort(Response::HTTP_FORBIDDEN, 'Cette action n\'est pas autorisée');
        }

        $authenticatedUser = $tokenable->withAccessToken($accessToken);

        Auth::setUser($authenticatedUser);
        $request->setUserResolver(static fn () => $authenticatedUser);

        return $next($request);
    }

    /**
     * Reuse the token Sanctum already loaded for this request instead of
     * querying it a second time, but only when it IS the request's bearer
     * token AND was looked up during this very request. A user memoised by the
     * guard from an earlier request (tests, long-lived workers), a session user
     * (transient token) or any other token falls back to the authoritative
     * lookup, exactly as before: a revoked token is still caught.
     */
    private function resolveAccessToken(Request $request, string $plainTextToken): ?PersonalAccessToken
    {
        $current = $request->user()?->currentAccessToken();

        if ($current instanceof AppPersonalAccessToken
            && $current->wasResolvedFor($request)
            && $this->matchesBearer($current, $plainTextToken)) {
            return $current;
        }

        return PersonalAccessToken::findToken($plainTextToken);
    }

    private function matchesBearer(PersonalAccessToken $accessToken, string $plainTextToken): bool
    {
        $secret = str_contains($plainTextToken, '|')
            ? explode('|', $plainTextToken, 2)[1]
            : $plainTextToken;

        return hash_equals((string) $accessToken->token, hash('sha256', $secret));
    }

    private function isExpired(PersonalAccessToken $accessToken): bool
    {
        $expiration = config('sanctum.expiration');

        if ($expiration && ! $accessToken->created_at->gt(now()->subMinutes((int) $expiration))) {
            return true;
        }

        return $accessToken->expires_at !== null && $accessToken->expires_at->isPast();
    }
}
