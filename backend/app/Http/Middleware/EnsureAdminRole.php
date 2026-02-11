<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated admin has one of the allowed roles.
     * Must be used after auth:sanctum and admin middlewares.
     * Returns 403 Forbidden if the admin's role is not in the allowed list.
     *
     * Usage: middleware('admin.role:superadmin,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user instanceof Admin || ! in_array($user->role->value, $roles)) {
            abort(403, "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.");
        }

        return $next($request);
    }
}
