<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\DeliverableValidationStatus;
use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ugc\RejectDeliverableRequest;
use App\Http\Requests\Ugc\ValidateDeliverableRequest;
use App\Http\Resources\DeliverableResource;
use App\Http\Resources\DeliverableReviewResource;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Producer;
use App\Services\Ugc\UgcDeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
     * Inbox de validation (écran 5A, UGC 4.4) : livrables in_review du Producteur
     * authentifié, agrégés sur les deux types d'owner. Asymétrie FK : booking
     * via users.id, candidature via producers.id (mission.producer_id). Ordonnés
     * par submitted_at ASC (le plus urgent côté SLA d'abord). Pure couche read.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $producer = $user->userable;
        abort_unless($producer instanceof Producer, 403, 'Réservé aux Producteurs.');

        $deliverables = Deliverable::query()
            ->where('validation_status', DeliverableValidationStatus::InReview)
            ->where(function ($q) use ($user, $producer): void {
                $q->whereHasMorph('owner', [Booking::class], function ($b) use ($user): void {
                    $b->where('producer_id', $user->id);
                })->orWhereHasMorph('owner', [Candidature::class], function ($c) use ($producer): void {
                    $c->whereHas('mission', fn ($m) => $m->where('producer_id', $producer->id));
                });
            })
            ->with(['owner' => function ($morphTo): void {
                $morphTo->morphWith([
                    Booking::class => ['face.userable'],
                    Candidature::class => ['face', 'mission'],
                ]);
            }])
            ->orderBy('submitted_at')
            ->get();

        return DeliverableReviewResource::collection($deliverables);
    }

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
