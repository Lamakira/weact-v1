<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Models\UgcSuspension;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actions de sortie de suspension douce UGC de la Face (story 5.3, backend seul) :
 * « terminer en retard » (resume — rouvre le tunnel sans dégeler) et « faire appel »
 * (appeal — none→pending, revue admin). Le middleware `face` garantit une Face
 * authentifiée → pas de garde 403 manuelle (calque UgcSuspensionStatusController 5.2).
 * La résolution de la suspension active = même garde que isUgcSuspended
 * (where face_id … whereNull('reactivated_at')).
 */
class UgcSuspensionActionController extends Controller
{
    public function __construct(
        private readonly UgcSuspensionService $service,
    ) {}

    public function resume(Request $request): JsonResponse
    {
        $face = $request->user()->userable;

        $suspension = UgcSuspension::query()
            ->where('face_id', $face->getKey())
            ->whereNull('reactivated_at')
            ->first();

        if ($suspension === null) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucune suspension active sur ton compte.'),
                422
            );
        }

        return match ($this->service->resumeForLateCompletion($suspension)) {
            'resumed' => response()->json([
                'message' => 'Tu peux maintenant déposer ta vidéo en retard pour terminer ce deal.',
            ]),
            'window_closed' => response()->json(
                ErrorCodes::InvalidStatus->envelope(
                    'La fenêtre de régularisation (30 jours) est dépassée. Tu peux faire appel auprès de WeAct.'
                ),
                422
            ),
            'already_resumed' => response()->json(
                ErrorCodes::InvalidStatus->envelope('Ce deal a déjà été repris.'),
                422
            ),
            default => response()->json( // 'deal_unavailable' | 'no_active_suspension'
                ErrorCodes::InvalidStatus->envelope("Ce deal n'est plus disponible pour une reprise."),
                422
            ),
        };
    }

    public function appeal(Request $request): JsonResponse
    {
        $face = $request->user()->userable;

        $suspension = UgcSuspension::query()
            ->where('face_id', $face->getKey())
            ->whereNull('reactivated_at')
            ->first();

        if ($suspension === null) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucune suspension active sur ton compte.'),
                422
            );
        }

        return match ($this->service->openAppeal($suspension)) {
            'opened' => response()->json([
                'message' => "Ton appel est enregistré — l'équipe WeAct le revoit sous ~24 h.",
            ]),
            default => response()->json( // 'appeal_exists' | 'no_active_suspension'
                ErrorCodes::InvalidStatus->envelope('Un appel est déjà enregistré pour cette suspension.'),
                422
            ),
        };
    }
}
