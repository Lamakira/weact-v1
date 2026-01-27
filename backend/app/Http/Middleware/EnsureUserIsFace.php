<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Face;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsFace
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is a Face (not a Producer or Admin).
     * Returns 403 Forbidden if the user is not a Face.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->userable_type !== Face::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        return $next($request);
    }
}
