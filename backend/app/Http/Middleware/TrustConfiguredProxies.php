<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * TrustProxies dont la liste des proxys est lue à chaque requête depuis la config
 * (`app.trusted_proxies`, alimentée par TRUSTED_PROXIES) : `bootstrap/app.php`
 * s'exécute trop tôt pour y lire l'environnement de façon fiable.
 *
 * Valeur vide = aucun proxy de confiance : `$request->ip()` reste REMOTE_ADDR et
 * les en-têtes X-Forwarded-* sont ignorés (cas de la prod : nginx → FastCGI).
 *
 * X-Forwarded-Host n'est volontairement PAS approuvé : un Host usurpé
 * contaminerait les réponses publiques mises en cache.
 */
class TrustConfiguredProxies extends TrustProxies
{
    /** @var int */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $proxies = config('app.trusted_proxies');

        return is_string($proxies) && trim($proxies) === '' ? null : $proxies;
    }
}
