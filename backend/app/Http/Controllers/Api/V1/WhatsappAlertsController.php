<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Consentement de l'utilisateur connecté (Face ou Producteur) aux alertes WhatsApp.
 */
class WhatsappAlertsController extends Controller
{
    public function __construct(private readonly WhatsappService $whatsapp) {}

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();

        if (! $this->whatsapp->setEnabled($user, (bool) $validated['enabled'])) {
            return response()->json([
                'error' => [
                    'code' => 'WHATSAPP_NUMBER_REQUIRED',
                    'message' => 'Ajoutez d\'abord votre numéro WhatsApp.',
                ],
            ], 422);
        }

        return response()->json(['data' => $this->whatsapp->alertsPayload($user->refresh())]);
    }

    public function dismissInvite(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->whatsapp->dismissInvite($user);

        return response()->noContent();
    }
}
