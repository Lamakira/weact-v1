<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature flag for the JSON endpoints of the Google flow.
 *
 * A middleware (not a controller check) so the 403 precedes FormRequest
 * validation: with the flag off, a malformed body must not get a 422. The callback
 * is deliberately NOT behind it — it is a browser navigation and bounces to the SPA.
 */
class EnsureGoogleOAuthEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('services.google.enabled', false)) {
            return response()->json([
                'error' => [
                    'code' => 'GOOGLE_OAUTH_DISABLED',
                    'message' => 'La connexion avec Google est indisponible.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
