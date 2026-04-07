<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Face;
use App\Models\Producer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsFaceOrProducer
{
    /**
     * Ensure the authenticated user is a Face or Producer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->userable_type, [Face::class, Producer::class], true)) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        return $next($request);
    }
}
