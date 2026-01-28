<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Producer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsProducer
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is a Producer (not a Face or Admin).
     * Returns 403 Forbidden if the user is not a Producer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->userable_type !== Producer::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        return $next($request);
    }
}
