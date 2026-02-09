<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is an Admin (not a User).
     * Must be used after auth:sanctum middleware.
     * Returns 403 Forbidden if the user is not an Admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user instanceof Admin) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        return $next($request);
    }
}
