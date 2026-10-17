<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\PublicViewer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification FACULTATIVE d'un endpoint public dont la réponse dépend du visiteur
 * (lot B13 : nom complet des Faces pour un Producer connecté). Ne refuse jamais : sans
 * jeton valide de Producer, la requête est servie en invité.
 *
 * À placer AVANT `public.cache` sur la route : la classe résolue fait partie de la clé de
 * cache. La réponse porte `Vary: Authorization`, et une réponse Producer est marquée
 * `private, no-store` pour qu'aucun cache HTTP partagé ne la conserve.
 */
class ResolvePublicViewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $viewer = PublicViewer::resolve($request);

        $response = $next($request);

        $response->headers->set('Vary', 'Authorization', false);

        if ($viewer === PublicViewer::PRODUCER) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
