<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle` standard + exemption explicite d'IP (config app.throttle_bypass_ips).
 *
 * TEMPORAIRE, pour les tests de charge depuis une machine unique : la liste est
 * vide par défaut (aucun effet). Correspondance stricte de chaîne (pas de CIDR,
 * pas de joker). Aucun log par requête (volume).
 */
class ThrottleRequestsWithBypass extends ThrottleRequests
{
    /**
     * Arguments variadiques : le parent distingue le limiteur nommé
     * (`throttle:login`) via func_num_args() === 3, il faut donc les relayer tels quels.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  ...$args  [$maxAttempts, $decayMinutes, $prefix] ou [$limiterName]
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next, ...$args)
    {
        /** @var list<string> $bypass */
        $bypass = config('app.throttle_bypass_ips', []);

        if ($bypass !== [] && in_array((string) $request->ip(), $bypass, true)) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$args);
    }
}
