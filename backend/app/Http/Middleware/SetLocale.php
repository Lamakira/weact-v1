<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la locale Laravel à `fr` sur toutes les requêtes API.
 *
 * Source de vérité projet : `fr` est la langue par défaut du backend (cf. config/app.php
 * et FIX-22.1). Le défaut config suffit en théorie, mais ce middleware protège contre un
 * override explicite de la locale à l'intérieur d'une requête HTTP (ex. test qui appelle
 * `App::setLocale('en')` avant la requête) en la remettant à `fr` en début de pipeline.
 *
 * Portée limitée : ce middleware ne tourne QUE sur les routes du groupe `api`. Les jobs,
 * commandes Artisan et handlers hors HTTP ne le traversent pas — ils dépendent uniquement
 * du défaut `config/app.php`.
 *
 * Choix Carbon (FIX-22.1 AC #9) — Option B : `Carbon::setLocale('fr')` reste posé une fois
 * pour toutes dans `AppServiceProvider::boot()`. Ce middleware ne touche pas à Carbon afin
 * de garder une seule source de vérité par couche (App = par requête, Carbon = au boot).
 *
 * Hors scope (re-scope FIX-22.2) : `ThrottleRequestsException` ("Too Many Attempts.") est
 * hardcodée vendor et n'est PAS traduite par la couche `Lang` — forcer la locale ici
 * n'affecte pas ce message. Idem pour `AuthenticationException` et `TokenMismatchException`.
 * La normalisation FR de ces exceptions vendor sera traitée dans FIX-22.2 (format unifié
 * d'erreur API).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('fr');

        return $next($request);
    }
}
