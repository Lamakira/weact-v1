<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\ErrorCodes;
use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\IndexMissionCandidaturesRequest;
use App\Http\Resources\CandidatureResource;
use App\Http\Resources\ProducerCandidatureResource;
use App\Mail\CandidatureAcceptedMail;
use App\Mail\CandidatureRefusedMail;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Services\FaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CandidatureController extends Controller
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * List all candidatures for a specific mission.
     *
     * Returns paginated candidatures with face details.
     * Only the mission owner (Producer) can access this endpoint.
     * Supports optional status filter via query parameter.
     */
    public function index(IndexMissionCandidaturesRequest $request, Mission $mission): AnonymousResourceCollection
    {
        $user = $request->user();
        $producer = $user->userable;

        // Authorization: mission must belong to this Producer
        if ($mission->producer_id !== $producer->id) {
            abort(403, 'Cette mission ne vous appartient pas');
        }

        $query = Candidature::where('mission_id', $mission->id)
            ->with(['face.activeSubscription', 'conversation', 'shipment', 'deliverables'])
            ->latest();

        // Apply status filter if provided
        $statusFilter = $request->getStatusFilter();
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $candidatures = $query->paginate(15);

        return ProducerCandidatureResource::collection($candidatures);
    }

    /**
     * Accept a UGC candidature (product-only — free, no escrow).
     *
     * Replaces the former Face auto-acceptance (ugc-8-2, D-8.2.a): the Producer
     * explicitly accepts a pending candidature, which lands "accepted". Capacity
     * is bounded here (engaged = accepted|confirmed|in_progress|completed,
     * D-8.2.b) and the mission auto-closes when full. The hybrid path (payment at
     * acceptance) is deferred to ugc-8-4 → 422 until then.
     */
    public function accept(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Verify user is a Producer
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Accès réservé aux Producteurs');
        }

        $producer = $user->userable;

        $candidature->loadMissing('mission');
        $mission = $candidature->mission;

        // Verify candidature's mission belongs to this Producer
        if ($mission->producer_id !== $producer->id) {
            abort(403, 'Cette candidature ne concerne pas une de vos missions');
        }

        // L'acceptation manuelle est réservée à l'UGC (le cash standard accepte
        // via l'effet de bord du paiement escrow, intouchable ici).
        if ($mission->type_mission !== MissionType::Ugc) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                "L'acceptation manuelle d'une candidature est réservée aux missions UGC."
            ), 422);
        }

        // Hybride (paiement à l'acceptation → escrow par-Face) différé à ugc-8-4.
        if ($mission->type_compensation !== CompensationType::Product) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                "L'acceptation d'une candidature sur une mission hybride sera bientôt disponible."
            ), 422);
        }

        if ($candidature->status !== CandidatureStatus::Pending) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'Seules les candidatures en attente peuvent être acceptées.'
            ), 422);
        }

        // ugc-8-2 (D-2.4.c restauré en revue) : l'engagement exige l'éligibilité
        // ACTUELLE de la Face — re-check abonnement + genre AU MOMENT de l'accept
        // (calque l'auto-accept supprimé). L'apply a pu dater : le Producteur review
        // ses candidatures après date_limite_candidature.
        $candidature->loadMissing('face');
        $face = $candidature->face;

        if (! $this->entitlement->canAccessUgc($face)) {
            return response()->json(ErrorCodes::UgcSubscriptionRequired->envelope(
                "Cette Face n'a plus d'abonnement actif lui donnant accès aux missions UGC."
            ), 403);
        }

        if ($mission->genre_voulu !== MissionGender::Tous) {
            $faceSexe = $face->sexe;
            if ($faceSexe === null || $faceSexe->value !== $mission->genre_voulu->value) {
                return response()->json(ErrorCodes::InvalidStatus->envelope(
                    'Le profil de cette Face ne correspond pas au genre requis par la mission.'
                ), 422);
            }
        }

        // Cœur transactionnel : capacité + écriture + auto-clôture, sérialisés par le lock mission (calque D-2.4.d).
        $result = DB::transaction(function () use ($mission, $candidature): array {
            /** @var Mission $lockedMission */
            $lockedMission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($lockedMission->status !== MissionStatus::Published) {
                return ['outcome' => 'full']; // auto-fermée à capacité (Closed) ou état non engageable
            }

            $engagedCount = Candidature::where('mission_id', $lockedMission->id)
                ->whereIn('status', [
                    CandidatureStatus::Accepted->value,    // ← NOUVEL état engagé (D-8.2.b)
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                    CandidatureStatus::Completed->value,
                ])
                ->count();

            if ($engagedCount >= $lockedMission->nombre_faces_voulu) {
                return ['outcome' => 'full'];
            }

            /** @var Candidature $locked */
            $locked = Candidature::where('id', $candidature->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CandidatureStatus::Pending) {
                return ['outcome' => 'already'];
            }

            $locked->update(['status' => CandidatureStatus::Accepted]);
            Conversation::firstOrCreate(['candidature_id' => $locked->id]);

            if ($engagedCount + 1 >= $lockedMission->nombre_faces_voulu) {
                $lockedMission->update(['status' => MissionStatus::Closed]);
            }

            return ['outcome' => 'accepted', 'candidature' => $locked];
        });

        if ($result['outcome'] === 'accepted') {
            /** @var Candidature $accepted */
            $accepted = $result['candidature'];
            $accepted->loadMissing(['face.user', 'mission']);

            // Notif in-app Face (non-fatal, post-commit) — calque reject:94-104.
            try {
                Notification::create([
                    'user_id' => $accepted->face->user->id,
                    'type' => 'candidature_accepted',
                    'data' => [
                        'mission_title' => $accepted->mission->titre,
                        'candidature_id' => $accepted->id,
                        'message' => 'Votre candidature a été acceptée — confirmez votre participation.',
                        'url' => '/face/candidatures',
                    ],
                ]);
            } catch (\Throwable) {
                // Non-fatal : l'acceptation est déjà persistée.
            }

            // Email Face (non-fatal, post-commit) — calque confirm:267-288.
            try {
                $faceEmail = trim((string) $accepted->face->user->email);
                if ($faceEmail !== '') {
                    Mail::to($faceEmail)->queue(new CandidatureAcceptedMail(
                        faceName: (string) $accepted->face->prenom,
                        missionTitle: (string) $accepted->mission->titre,
                        productName: (string) $accepted->mission->nom_produit,
                        reconfirmUrl: rtrim((string) config('app.frontend_url'), '/').'/face/candidatures',
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('CandidatureAcceptedMail queue failed', [
                    'candidature_id' => $accepted->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'data' => new CandidatureResource($accepted),
                'message' => 'Candidature acceptée',
            ]);
        }

        return match ($result['outcome']) {
            'full' => response()->json(ErrorCodes::MissionFull->envelope(
                'Toutes les places de cette mission sont déjà pourvues.'
            ), 422),
            'already' => response()->json(ErrorCodes::AlreadyAccepted->envelope(
                'Cette candidature a déjà été traitée.'
            ), 422),
        };
    }

    /**
     * Reject a candidature.
     *
     * Changes the candidature status from "pending" to "rejected".
     * Only the mission owner (Producer) can reject candidatures.
     * Only pending candidatures can be rejected.
     */
    public function reject(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Verify user is a Producer
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Accès réservé aux Producteurs');
        }

        $producer = $user->userable;

        // Eager load mission to avoid N+1 query
        $candidature->loadMissing('mission');

        // Verify candidature's mission belongs to this Producer
        if ($candidature->mission->producer_id !== $producer->id) {
            abort(403, 'Cette candidature ne concerne pas une de vos missions');
        }

        // Verify candidature is pending
        if ($candidature->status !== CandidatureStatus::Pending) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Seules les candidatures en attente peuvent être refusées',
                ],
            ], 400);
        }

        // Update status
        $candidature->status = CandidatureStatus::Rejected;
        $candidature->save();

        // Create notification for the Face
        $candidature->loadMissing('face.user');
        Notification::create([
            'user_id' => $candidature->face->user->id,
            'type' => 'candidature_rejected',
            'data' => [
                'mission_title' => $candidature->mission->titre,
                'candidature_id' => $candidature->id,
                'message' => 'Votre candidature a été refusée',
            ],
        ]);

        // Email refus — UGC uniquement (FR6) ; le standard reste in-app seul (non-régression). Non-fatal.
        if ($candidature->mission->type_mission === MissionType::Ugc) {
            try {
                $faceEmail = trim((string) $candidature->face->user->email);
                if ($faceEmail !== '') {
                    Mail::to($faceEmail)->queue(new CandidatureRefusedMail(
                        faceName: (string) $candidature->face->prenom,
                        missionTitle: (string) $candidature->mission->titre,
                        browseUrl: rtrim((string) config('app.frontend_url'), '/').'/face/missions',
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('CandidatureRefusedMail queue failed', [
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => new CandidatureResource($candidature),
            'message' => 'Candidature refusée',
        ]);
    }
}
