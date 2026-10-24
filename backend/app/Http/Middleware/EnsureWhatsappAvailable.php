<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ErrorCodes;
use App\Services\Whatsapp\WhatsappClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les routes /me/whatsapp-alerts n'existent pas tant que la fonctionnalité n'est pas disponible
 * (désactivée ou identifiants Meta absents) : 404, avant toute validation.
 */
class EnsureWhatsappAvailable
{
    public function __construct(private readonly WhatsappClient $client) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->client->isAvailable()) {
            return response()->json(ErrorCodes::NotFound->envelope('Ressource introuvable.'), 404);
        }

        return $next($request);
    }
}
