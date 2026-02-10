<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is a SuperAdmin.
     * Must be used after auth:sanctum and admin middlewares.
     * Returns 403 Forbidden if the user is not a SuperAdmin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user instanceof Admin || $user->role !== AdminRole::SuperAdmin) {
            abort(403, 'Seuls les super-administrateurs peuvent accéder à cette ressource.');
        }

        return $next($request);
    }
}
