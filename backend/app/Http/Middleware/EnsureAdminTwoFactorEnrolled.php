<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorEnrolled
{
    /**
     * 1. No CONFIRMED TOTP enrolment: the admin may log in but cannot use any admin
     *    route except enrolment and logout (declared outside this group) -> 403.
     * 2. Confirmed 2FA: the token itself must carry the exact `2fa` ability, i.e. it
     *    was issued after a successful second factor. Tokens issued before the
     *    enrolment (or legacy `*` tokens) are refused -> 401, the SPA logs out.
     *    The exact ability is checked: Sanctum's tokenCan() treats `*` as everything.
     * Must be used after auth:sanctum and admin middlewares.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Vous devez activer la double authentification pour continuer.',
                    'code' => 'ADMIN_2FA_REQUIRED',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken
            || ! in_array(Admin::ABILITY_TWO_FACTOR, $token->abilities ?? [], true)) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Session invalide. Veuillez vous reconnecter avec votre code de vérification.',
                    'code' => 'ADMIN_2FA_SESSION_INVALID',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
