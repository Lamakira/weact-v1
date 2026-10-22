<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CloseMissionRequest;
use App\Http\Requests\Mission\CompleteMissionRequest;
use App\Http\Requests\Mission\DeleteMissionRequest;
use App\Http\Requests\Mission\IndexProducerMissionsRequest;
use App\Http\Requests\Mission\ReopenMissionRequest;
use App\Http\Requests\Mission\ReplaceFreedPlaceRequest;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Requests\Mission\UpdateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\FreedPlaceService;
use App\Services\MissionService;
use App\Services\MissionSummaryService;
use App\Support\LifecycleSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly MissionService $missionService,
    ) {}

    /**
     * Display a listing of the producer's missions.
     * Filtered by authenticated producer, ordered by most recent first by default.
     *
     * Contrat historique conservé : sans `page` ni `per_page`, la réponse reste le
     * tableau complet non paginé. La pagination (data/links/meta) est opt-in ; `sort`,
     * `direction` et `status` s'appliquent dans les deux modes.
     */
    public function index(IndexProducerMissionsRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only Producers can access this endpoint
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        $query = Mission::where('producer_id', $user->userable_id)
            ->with(['producer' => fn ($q) => $q->withRatingAggregates()])
            ->withPaidPaymentFlag()
            ->withCount('candidatures');

        if ($request->validated('status') === IndexProducerMissionsRequest::TO_PROCESS) {
            // Lot D7 : missions où le Producteur doit agir maintenant (Mission::toProcessForProducer).
            $query->toProcessForProducer($user->userable_id);
        } elseif ($request->validated('status') === IndexProducerMissionsRequest::ACTIVE) {
            // Lot F4 : « En cours » = tout ce qui est vivant (hors brouillon et terminé).
            $query->whereIn('status', array_map(
                fn (MissionStatus $status): string => $status->value,
                IndexProducerMissionsRequest::ACTIVE_STATUSES,
            ));
        } elseif ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }

        $sort = $request->validated('sort', 'created_at');
        $direction = $request->validated('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['date_tournage', 'date_limite_candidature'], true)) {
            // Missions UGC : date de tournage NULL => toujours en fin de liste.
            $query->orderByRaw("`{$sort}` IS NULL");
        }
        if ($sort === 'status') {
            // Ordre de cycle de vie explicite (pas l'index de la colonne ENUM).
            LifecycleSort::apply($query, 'status', MissionStatus::lifecycleOrder(), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('id', $direction);

        if ($request->wantsPagination()) {
            $perPage = (int) $request->validated('per_page', IndexProducerMissionsRequest::DEFAULT_PER_PAGE);
            $paginator = $query->paginate($perPage);

            return MissionResource::collection($paginator)
                ->additional(['message' => $paginator->isEmpty()
                    ? 'Aucune mission trouvée'
                    : 'Missions récupérées avec succès'])
                ->response();
        }

        $missions = $query->get();

        $message = $missions->isEmpty()
            ? 'Aucune mission trouvée'
            : 'Missions récupérées avec succès';

        return response()->json([
            'data' => MissionResource::collection($missions),
            'message' => $message,
        ]);
    }

    /**
     * Display the specified mission.
     * For producer routes, only the owner can view their mission details.
     */
    public function show(Request $request, Mission $mission, FreedPlaceService $freedPlaces, MissionSummaryService $summaries): JsonResponse
    {
        $user = $request->user();

        // For producer routes, check ownership
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        if ($user->userable_id !== $mission->producer_id) {
            abort(403, 'Cette mission ne vous appartient pas');
        }

        // Lot B4 : places libérées (détail seulement : pas de requête par ligne sur les listes).
        $freedCount = FreedPlaceService::pendingFreedEntries($mission)->count();
        $mission->setAttribute('freed_places_count', $freedCount);
        $mission->setAttribute('can_replace_freed_place', $freedPlaces->canReplaceNow($mission, $freedCount));

        // Lot C1 : résumé du cycle de vie (barre d'étapes), détail Producer uniquement.
        $mission->load(['producer', 'productPhotos']);
        $summary = $summaries->summarize($mission, $freedCount);
        $mission->setAttribute('summary', $summary);
        // Valeurs déjà connues du résumé : évitent de refaire completionBlocker() et l'existence du paiement.
        $mission->setAttribute('can_complete', $mission->hasCompletableStatus() && $summary['completion_blocker'] === null);
        $mission->setAttribute('has_paid_payment', $summary['payment']['status'] === 'paid');

        return response()->json([
            'data' => new MissionResource($mission),
        ]);
    }

    /**
     * Lot B4 (D2) : donne une place libérée à une candidature non retenue de la mission.
     */
    public function replaceFreedPlace(ReplaceFreedPlaceRequest $request, Mission $mission, FreedPlaceService $freedPlaces): JsonResponse
    {
        $candidature = $freedPlaces->replace($mission, (string) $request->validated('candidature_id'));

        return response()->json([
            'data' => ['candidature_id' => $candidature->uuid, 'status' => $candidature->status->value],
            'message' => 'Place proposée à la remplaçante : elle a 48 h pour confirmer.',
        ]);
    }

    /**
     * Store a newly created mission.
     */
    public function store(StoreMissionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Get the Producer from the polymorphic relationship
        $producer = Producer::findOrFail($user->userable_id);

        $mission = $this->missionService->createMission(
            $producer,
            $request->validated()
        );

        $message = $mission->status === MissionStatus::PendingPayment
            ? 'Mission UGC créée. Réglez la commission pour la publier.'
            : 'Mission publiée avec succès';

        return response()->json([
            'data' => new MissionResource($mission->load('productPhotos')),
            'message' => $message,
        ], 201);
    }

    /**
     * Update the specified mission.
     * Authorization is handled by UpdateMissionRequest::authorize()
     * Status validation is handled by UpdateMissionRequest::withValidator()
     */
    public function update(UpdateMissionRequest $request, Mission $mission): JsonResponse
    {
        $updatedMission = $this->missionService->updateMission(
            $mission,
            $request->validated()
        );

        return response()->json([
            'data' => new MissionResource($updatedMission->load('producer')),
            'message' => 'Mission modifiée avec succès',
        ]);
    }

    /**
     * Delete the specified mission.
     * Authorization is handled by DeleteMissionRequest::authorize()
     * Status validation is handled by DeleteMissionRequest::withValidator()
     *
     * @param  DeleteMissionRequest  $request  Type-hint triggers FormRequest validation (not used directly)
     */
    public function destroy(DeleteMissionRequest $request, Mission $mission): JsonResponse
    {
        $this->missionService->deleteMission($mission);

        return response()->json([
            'message' => 'Mission supprimée avec succès',
        ]);
    }

    /**
     * Close a mission to stop accepting new candidatures.
     * Authorization is handled by CloseMissionRequest::authorize()
     * Status validation is handled by CloseMissionRequest::withValidator()
     *
     * @param  CloseMissionRequest  $request  Type-hint triggers FormRequest validation (not used directly)
     */
    public function close(CloseMissionRequest $request, Mission $mission): JsonResponse
    {
        $closedMission = $this->missionService->closeMission($mission);

        return response()->json([
            'data' => new MissionResource($closedMission->load('producer')),
            'message' => 'Mission clôturée avec succès',
        ]);
    }

    /**
     * Reopen a closed mission to accept candidatures again.
     * Authorization is handled by ReopenMissionRequest::authorize()
     * Status validation is handled by ReopenMissionRequest::withValidator()
     *
     * @param  ReopenMissionRequest  $request  Type-hint triggers FormRequest validation (not used directly)
     */
    public function reopen(ReopenMissionRequest $request, Mission $mission): JsonResponse
    {
        $reopenedMission = $this->missionService->reopenMission($mission);

        return response()->json([
            'data' => new MissionResource($reopenedMission->load('producer')),
            'message' => 'Mission réouverte avec succès',
        ]);
    }

    /**
     * Mark a mission as completed.
     * Authorization is handled by CompleteMissionRequest::authorize()
     * Status validation is handled by CompleteMissionRequest::withValidator()
     *
     * @param  CompleteMissionRequest  $request  Type-hint triggers FormRequest validation (not used directly)
     */
    public function complete(CompleteMissionRequest $request, Mission $mission): JsonResponse
    {
        $completedMission = $this->missionService->completeMission($mission);

        return response()->json([
            'data' => new MissionResource($completedMission->load('producer')),
            'message' => 'Mission marquée comme terminée',
        ]);
    }
}
