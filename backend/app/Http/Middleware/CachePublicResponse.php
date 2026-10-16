<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short response cache for the public GET endpoints, whose payload is the SAME
 * for every visitor (no read of the request user, forced `fr` locale).
 *
 * Why: each public request costs 30-60 ms of PHP even with an idle database,
 * so a handful of identical hits saturate the CPU. A hit here skips the
 * controller, the queries and the resource serialization.
 *
 * Contract:
 *  - GET / HEAD only; key = path + query params sorted by name (values as given),
 *    so `?b=1&a=2` and `?a=2&b=1` share an entry. The faces `generation` param is
 *    part of the query: a cached page always matches ONE generation.
 *  - Only 200 JSON responses are stored: [status, content, content-type].
 *    Errors (404...) are never cached. Set-Cookie is neither stored nor replayed
 *    (session / CSRF cookies are added by outer middleware, on hits too).
 *  - NO invalidation: TTL-based staleness (default 60 s) is accepted by the PO —
 *    a profile edit, a mission closing or a moderation shows up within a minute.
 *  - `cache.public_response_ttl <= 0` disables the middleware (pass-through).
 *
 * Only attach it to routes whose output does NOT depend on the viewer.
 */
class CachePublicResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $ttl = (int) config('cache.public_response_ttl', 0);

        if ($ttl <= 0 || ! $request->isMethodCacheable()) {
            return $next($request);
        }

        $store = Cache::store(config('cache.public_response_store'));
        $key = $this->cacheKey($request);

        $cached = $store->get($key);

        if (is_array($cached) && count($cached) === 3) {
            [$status, $content, $contentType] = $cached;

            return response($content, $status, [
                'Content-Type' => $contentType,
                'X-Public-Cache' => 'HIT',
            ]);
        }

        $response = $next($request);

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($response->getStatusCode() === 200 && str_contains($contentType, 'json')) {
            $store->put($key, [200, (string) $response->getContent(), $contentType], $ttl);
        }

        $response->headers->set('X-Public-Cache', 'MISS');

        return $response;
    }

    private function cacheKey(Request $request): string
    {
        $query = $request->query();
        $this->ksortRecursive($query);

        return 'public-response:'.sha1($request->path().'?'.json_encode($query));
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function ksortRecursive(array &$values): void
    {
        ksort($values);

        foreach ($values as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }
}
