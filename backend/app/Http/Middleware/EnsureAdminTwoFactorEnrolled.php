<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorEnrolled
{
    /**
     * An admin without a CONFIRMED TOTP enrolment may log in but cannot use any
     * admin route except the enrolment endpoints and logout (declared outside
     * the group guarded by this middleware).
     * Must be used after auth:sanctum and admin middlewares.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Admin && ! $user->hasTwoFactorEnabled()) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Vous devez activer la double authentification pour continuer.',
                    'code' => 'ADMIN_2FA_REQUIRED',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
