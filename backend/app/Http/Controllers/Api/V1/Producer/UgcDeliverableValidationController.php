<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ugc\RejectDeliverableRequest;
use App\Http\Requests\Ugc\ValidateDeliverableRequest;
use App\Http\Resources\DeliverableResource;
use App\Models\Deliverable;
use App\Services\Ugc\UgcDeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Décision Producteur sur un livrable UGC (FR7, tunnel étape 5/6, story 4.3) :
 * valider / rejeter / demander une retouche. Le FormRequest filtre « est un
 * Producteur » ; la propriété du deal est vérifiée par Gate::authorize('review',
 * $deliverable) (DeliverablePolicy). La logique transactionnelle (transitions
 * tunnel, chrono Avis, clôture, notif Face post-commit) vit dans
 * UgcDeliverableService.
 */
class UgcDeliverableValidationController extends Controller
{
    public function __construct(
        private readonly UgcDeliverableService $service,
    ) {}

    /**
     * Valide un livrable : Unboxing → démarre le chrono Avis (avis_pending) ;
     * Avis → clôture (completed, tunnel SEULEMENT — D-4.3.a).
     */
    public function validate(ValidateDeliverableRequest $request, Deliverable $deliverable): JsonResponse
    {
        Gate::authorize('review', $deliverable);

        return $this->respond($this->service->validate($deliverable), 'Livrable validé');
    }

    /**
     * Rejette un livrable (motif requis) : rouvre la fenêtre d'upload du même
     * kind, chrono Face conservé (D-4.3.b).
     */
    public function reject(RejectDeliverableRequest $request, Deliverable $deliverable): JsonResponse
    {
        Gate::authorize('review', $deliverable);

        return $this->respond($this->service->reject($deliverable, $request->string('review_note')->toString()), 'Livrable refusé');
    }

    /**
     * Demande une retouche (motif requis) : identique au rejet côté tunnel/chrono,
     * statut retouche_requested (D-4.3.i).
     */
    public function requestRetouche(RejectDeliverableRequest $request, Deliverable $deliverable): JsonResponse
    {
        Gate::authorize('review', $deliverable);

        return $this->respond($this->service->requestRetouche($deliverable, $request->string('review_note')->toString()), 'Retouche demandée');
    }

    /**
     * @param  array{outcome: string, deliverable?: Deliverable}  $result
     */
    private function respond(array $result, string $okMessage): JsonResponse
    {
        return match ($result['outcome']) {
            'validated', 'rejected', 'retouche_requested' => response()->json([
                'data' => new DeliverableResource($result['deliverable']),
                'message' => $okMessage,
            ], 200),
            'refund_in_progress' => response()->json(
                ErrorCodes::InvalidStatus->envelope('La commission de ce deal est en cours de remboursement — action impossible.'),
                422
            ),
            default => response()->json(
                ErrorCodes::InvalidStatus->envelope("Ce livrable n'est plus en attente de validation."),
                422
            ),
        };
    }
}
