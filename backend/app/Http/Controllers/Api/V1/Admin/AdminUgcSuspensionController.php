<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCodes;
use App\Enums\UgcSuspensionAppealStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUgcSuspensionResource;
use App\Models\UgcSuspension;
use App\Services\Ugc\UgcSuspensionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revue admin des appels de suspension douce UGC + réactivation (story 5.3).
 * index : file des suspensions actives dont l'appel est Pending. reactivate :
 * lève la suspension (accepte l'appel si Pending, sinon réactivation directe).
 * rejectAppeal : rejette l'appel (reste suspendu). Calque
 * AdminAttendanceDisputeController (paginate + meta). Binding {ugcSuspension} par
 * uuid (UgcSuspension use HasRouteUuid).
 */
class AdminUgcSuspensionController extends Controller
{
    public function __construct(
        private readonly UgcSuspensionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $suspensions = UgcSuspension::query()
            ->whereNull('reactivated_at')
            ->where('appeal_status', UgcSuspensionAppealStatus::Pending)
            ->with(['face', 'shipment.owner'])
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'data' => AdminUgcSuspensionResource::collection($suspensions->items()),
            'meta' => [
                'current_page' => $suspensions->currentPage(),
                'last_page' => $suspensions->lastPage(),
                'per_page' => $suspensions->perPage(),
                'total' => $suspensions->total(),
            ],
            'message' => 'Appels de suspension UGC récupérés avec succès',
        ]);
    }

    public function reactivate(UgcSuspension $ugcSuspension): JsonResponse
    {
        if ($ugcSuspension->reactivated_at !== null) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Cette suspension a déjà été réactivée.'),
                422
            );
        }

        $this->service->reactivate($ugcSuspension);
        $ugcSuspension->refresh()->loadMissing(['face', 'shipment.owner']);

        return response()->json([
            'data' => new AdminUgcSuspensionResource($ugcSuspension),
            'message' => 'Compte Face réactivé.',
        ]);
    }

    public function rejectAppeal(UgcSuspension $ugcSuspension): JsonResponse
    {
        return match ($this->service->rejectAppeal($ugcSuspension)) {
            'rejected' => response()->json([
                'data' => new AdminUgcSuspensionResource(
                    $ugcSuspension->refresh()->loadMissing(['face', 'shipment.owner'])
                ),
                'message' => 'Appel rejeté — la Face reste suspendue.',
            ]),
            'no_pending_appeal' => response()->json(
                ErrorCodes::InvalidStatus->envelope('Aucun appel en attente sur cette suspension.'),
                422
            ),
            default => response()->json( // 'no_active_suspension'
                ErrorCodes::InvalidStatus->envelope('Cette suspension a déjà été réactivée.'),
                422
            ),
        };
    }
}
