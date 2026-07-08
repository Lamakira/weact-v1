<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\ErrorCodes;
use App\Enums\EscrowStatus;
use App\Enums\MissionGender;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\UgcMissionDealAccepted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\StoreCandidatureRequest;
use App\Http\Resources\CandidatureResource;
use App\Http\Resources\FaceCandidatureResource;
use App\Mail\FaceConfirmedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\MissionPaymentService;
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
     * List all candidatures for the authenticated Face.
     *
     * Returns paginated candidatures with mission and producer details.
     * Supports optional status filter via query parameter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $face = $request->user()->userable;

        $query = Candidature::where('face_id', $face->id)
            ->with(['mission', 'mission.producer', 'conversation', 'shipment.receptionPhotos', 'deliverables'])
            ->latest();

        // Optional status filter
        if ($request->has('status') && $request->status !== '') {
            $status = CandidatureStatus::tryFrom($request->status);
            if ($status) {
                $query->where('status', $status);
            }
        }

        $candidatures = $query->paginate(15);

        return FaceCandidatureResource::collection($candidatures);
    }

    /**
     * Apply to a mission as a Face.
     *
     * Creates a candidature with status "pending" if:
     * - Mission is published and accepting candidatures
     * - Face hasn't already applied to this mission
     */
    public function store(StoreCandidatureRequest $request, Mission $mission): JsonResponse
    {
        // Check mission is published
        if ($mission->status !== MissionStatus::Published) {
            abort(404);
        }

        // is_active (3.0) : pas de nouvelle candidature vers un producteur désactivé.
        if (! $mission->hasActiveProducer()) {
            abort(404);
        }

        // Check mission is accepting candidatures (published + deadline not passed)
        if (! $mission->isAcceptingCandidatures()) {
            return response()->json([
                'error' => [
                    'code' => 'MISSION_CLOSED',
                    'message' => "Cette mission n'accepte plus de candidatures",
                ],
            ], 422);
        }

        // Get Face from authenticated user
        $face = $request->user()->userable;

        // Check if Face has already applied to this mission
        if (Candidature::where('face_id', $face->id)->where('mission_id', $mission->id)->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_APPLIED',
                    'message' => 'Vous avez déjà postulé à cette mission',
                ],
            ], 422);
        }

        // Gate UGC (FR5) : seules les Faces abonnées Starter+ postulent aux missions UGC.
        // Après le check duplicate : une Face détentrice d'une candidature reçoit
        // ALREADY_APPLIED, pas le paywall (cohérent avec l'exception candidature de show()).
        if ($mission->type_mission === MissionType::Ugc
            && ! $this->entitlement->canAccessUgc($face)) {
            return response()->json(
                ErrorCodes::UgcSubscriptionRequired->envelope(
                    "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."
                ),
                403
            );
        }

        // Check gender compatibility
        if ($mission->genre_voulu !== MissionGender::Tous) {
            $faceSexe = $face->sexe;

            if ($faceSexe === null) {
                return response()->json([
                    'error' => [
                        'code' => 'gender_mismatch',
                        'message' => 'Veuillez compléter votre profil (genre) avant de postuler.',
                    ],
                ], 422);
            }

            if ($faceSexe->value !== $mission->genre_voulu->value) {
                return response()->json([
                    'error' => [
                        'code' => 'gender_mismatch',
                        'message' => "Cette mission recherche un profil {$mission->genre_voulu->label()}. Votre profil ne correspond pas au genre requis.",
                    ],
                ], 422);
            }
        }

        // Create the candidature (status defaults to 'pending')
        $candidature = Candidature::create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'message_motivation' => $request->validated('message_motivation'),
        ]);

        // Notify producer of new candidature
        $mission->loadMissing('producer');
        $producerUser = User::where('userable_type', \App\Models\Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->first();

        if ($producerUser) {
            try {
                Notification::create([
                    'user_id' => $producerUser->id,
                    'type' => 'new_candidature',
                    'data' => [
                        'message' => "Nouvelle candidature reçue pour la mission \"{$mission->titre}\"",
                        'mission_id' => $mission->id,
                        'url' => "/producer/missions/{$mission->id}/candidatures",
                    ],
                ]);
            } catch (\Throwable) {
                // Non-fatal: notification failure should not block candidature creation
            }
        }

        return response()->json([
            'data' => new CandidatureResource($candidature),
            'message' => 'Candidature envoyée avec succès',
        ], 201);
    }

    /**
     * Confirm participation in a mission after candidature is accepted.
     *
     * Changes candidature status from "accepted" to "confirmed".
     * Only the Face who owns the candidature can confirm it.
     */
    public function confirm(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Verify user is a Face
        if ($user->userable_type !== Face::class) {
            abort(403, 'Accès réservé aux Faces');
        }

        $face = $user->userable;

        // Verify candidature belongs to this Face
        if ($candidature->face_id !== $face->id) {
            abort(403, 'Cette candidature ne vous appartient pas');
        }

        // Verify candidature is accepted
        if ($candidature->status !== CandidatureStatus::Accepted) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_STATUS',
                    'message' => 'Seules les candidatures acceptées peuvent être confirmées',
                ],
            ], 400);
        }

        // ugc-8-2 : une candidature UGC produit-seul est `Accepted` SANS MissionPayment
        // (acceptation gratuite). Elle se reconfirme via l'endpoint dédié `reconfirm` ;
        // la router ici tomberait sur le canary cash INVARIANT_VIOLATION (:218) en
        // faux positif. Garde explicite AVANT le lookup payment.
        $candidature->loadMissing('mission');
        if ($candidature->mission?->type_mission === MissionType::Ugc) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'Une candidature UGC se reconfirme via l\'endpoint dédié.'
            ), 422);
        }

        /** @var Mission $mission */
        $mission = $candidature->mission()->with('payment.entries')->firstOrFail();
        $missionPayment = $mission->payment;

        if (! $missionPayment || $missionPayment->status !== MissionPaymentStatus::Paid) {
            // FIX-20.2: under FIX-20.1's contract, an Accepted candidature always
            // points at a Paid payment. Reaching this branch signals a broken
            // invariant — log a greppable canary before returning.
            Log::warning('INVARIANT_VIOLATION: candidature accepted without paid payment', [
                'candidature_id' => $candidature->id,
                'mission_id' => $mission->id,
                'payment_id' => $missionPayment?->id,
                'payment_status' => $missionPayment?->status?->value,
            ]);

            return response()->json([
                'error' => [
                    'code' => 'PAYMENT_NOT_CONFIRMED',
                    'message' => 'Le paiement de la mission doit être confirmé avant de pouvoir confirmer votre participation',
                ],
            ], 422);
        }

        if (! $missionPayment->entries->contains('candidature_id', $candidature->id)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_IN_FINAL_SELECTION',
                    'message' => 'Cette candidature ne fait pas partie de la sélection finale du producteur',
                ],
            ], 422);
        }

        // Update status to confirmed
        $candidature->status = CandidatureStatus::Confirmed;
        $candidature->save();

        // Notify the producer that this face confirmed
        $producerUser = User::where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->first();

        if ($producerUser) {
            try {
                Notification::create([
                    'user_id' => $producerUser->id,
                    'type' => 'face_confirmed_participation',
                    'data' => [
                        'message' => $face->prenom.' a confirmé sa participation à la mission "'.$mission->titre.'".',
                        'mission_id' => $mission->id,
                        'url' => "/producer/missions/{$mission->id}/candidatures",
                    ],
                ]);
            } catch (\Throwable) {
                // Non-fatal: confirmation is already persisted
            }

            // FIX-24.3: best-effort confirmation email to Producer.
            // Non-fatal: confirmation + in-app notification remain the source of truth.
            try {
                $mission->loadMissing('producer');
                $producer = $mission->producer;
                $producerEmail = trim((string) $producerUser->email);

                if ($producer instanceof Producer && $producerEmail !== '') {
                    Mail::to($producerEmail)->queue(
                        new FaceConfirmedMail(
                            producer: $producer,
                            face: $face,
                            mission: $mission,
                        )
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('FaceConfirmedMail queue failed', [
                    'candidature_id' => $candidature->id,
                    'mission_id' => $mission->id,
                    'producer_user_id' => $producerUser->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Check if all selected faces have now confirmed → move candidatures to in_progress
        $missionPayment = $mission->payment;
        $selectedCandidatureIds = $missionPayment->entries->pluck('candidature_id');
        $pendingConfirmation = Candidature::whereIn('id', $selectedCandidatureIds)
            ->where('status', CandidatureStatus::Accepted)
            ->exists();

        if (! $pendingConfirmation) {
            // All selected faces confirmed — mark candidatures as in_progress
            Candidature::whereIn('id', $selectedCandidatureIds)
                ->where('status', CandidatureStatus::Confirmed)
                ->update(['status' => CandidatureStatus::InProgress->value]);
        }

        return response()->json([
            'data' => new CandidatureResource($candidature),
            'message' => 'Participation confirmée',
        ]);
    }

    /**
     * Reconfirm participation in a UGC mission after the Producer accepted (ugc-8-2, D-8.2.d).
     *
     * Changes candidature status from "accepted" to "confirmed". SÉPARÉ du confirm
     * cash standard (qui exige un MissionPayment Paid, absent en UGC produit-seul).
     * Le 2ᵉ oui de la Face arme le tunnel : dispatch UgcMissionDealAccepted (event
     * REPURPOSÉ, D-8.2.e) ⇒ le Producteur est notifié de préparer l'expédition.
     */
    public function reconfirm(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Verify user is a Face
        if ($user->userable_type !== Face::class) {
            abort(403, 'Accès réservé aux Faces');
        }

        $face = $user->userable;

        // Verify candidature belongs to this Face
        if ($candidature->face_id !== $face->id) {
            abort(403, 'Cette candidature ne vous appartient pas');
        }

        // La reconfirmation directe est réservée à l'UGC (le standard passe par confirm).
        $candidature->loadMissing('mission');
        if ($candidature->mission?->type_mission !== MissionType::Ugc) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'La reconfirmation directe est réservée aux missions UGC.'
            ), 422);
        }

        // Verify candidature is accepted
        if ($candidature->status !== CandidatureStatus::Accepted) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'Seules les candidatures acceptées peuvent être reconfirmées.'
            ), 422);
        }

        // ugc-8-4 (D-8.4.l) : une candidature hybride ne se reconfirme que si son escrow est
        // Locked (paiement confirmé par le webhook). Défense — en nominal une candidature
        // hybride n'est `accepted` que via le webhook qui locke l'escrow, donc la garde tient
        // toujours ; c'est un filet. Produit-seul (pas d'entry) : inchangé. La garde
        // type_mission ci-dessus (:350) garantit déjà mission non-null ⇒ `->`.
        if ($candidature->mission->type_compensation === CompensationType::Hybrid
            && $candidature->paymentEntry?->escrow_status !== EscrowStatus::Locked) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'Le règlement de cette candidature n\'est pas finalisé.'
            ), 422);
        }

        $confirmed = DB::transaction(function () use ($candidature): ?Candidature {
            /** @var Candidature $locked */
            $locked = Candidature::where('id', $candidature->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CandidatureStatus::Accepted) {
                return null; // idempotence : une requête concurrente a déjà reconfirmé
            }
            $locked->update(['status' => CandidatureStatus::Confirmed]);

            return $locked;
        });

        if ($confirmed === null) {
            return response()->json(ErrorCodes::InvalidStatus->envelope(
                'Cette candidature a déjà été reconfirmée.'
            ), 422);
        }

        // Post-commit : le deal est ON, le Producteur doit expédier (D-8.2.e, event REPURPOSÉ).
        UgcMissionDealAccepted::dispatch($confirmed);

        return response()->json([
            'data' => new CandidatureResource($confirmed),
            'message' => 'Participation reconfirmée — le producteur va expédier le produit',
        ]);
    }

    /**
     * Cancel a pending candidature (withdraw application).
     *
     * Changes candidature status from "pending" to "cancelled".
     * Only the Face who owns the candidature can cancel it.
     */
    public function cancel(Request $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Verify user is a Face
        if ($user->userable_type !== Face::class) {
            abort(403, 'Accès réservé aux Faces');
        }

        $face = $user->userable;

        // Verify candidature belongs to this Face
        if ($candidature->face_id !== $face->id) {
            abort(403, 'Cette candidature ne vous appartient pas');
        }

        // ugc-8-4 (D-8.4.h) / ugc-9-1 (D-9.1.k) : décline d'une candidature hybride payée
        // (accepted, escrow Locked) → dénouement complet via unwindUgcCandidatureSlot (refund
        // Producteur du net escrow + candidature Cancelled + RÉOUVERTURE du slot). Les autres
        // cas (produit-seul accepted, pending) tombent sur la garde pending-only ci-dessous. Une
        // candidature confirmed/en tunnel ne se décline pas ici — c'est le chemin deadline/suspension.
        $candidature->loadMissing('mission');
        if ($candidature->status === CandidatureStatus::Accepted
            && $candidature->mission?->type_compensation === CompensationType::Hybrid) {
            app(MissionPaymentService::class)->unwindUgcCandidatureSlot($candidature, 'ugc_face_declined');

            return response()->json([
                'message' => 'Candidature annulée — le producteur a été remboursé.',
            ]);
        }

        // Verify candidature is pending
        if ($candidature->status !== CandidatureStatus::Pending) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Seules les candidatures en attente peuvent être annulées.'),
                400
            );
        }

        // ugc-9-1 (D-9.1.j) : une candidature hybride Pending peut porter une entry escrow
        // Pending in-flight (paiement Producteur initié, webhook pas encore confirmé). On la
        // markFailed (supprime l'entry → libère le slot in-flight) AVANT le flip Cancelled.
        $entry = $candidature->paymentEntry;
        if ($entry !== null && $entry->escrow_status === EscrowStatus::Pending) {
            app(MissionPaymentService::class)->markUgcMissionCandidatureFailed($entry, 'face_cancelled_pending');
        }

        $candidature->update(['status' => CandidatureStatus::Cancelled]);

        return response()->json([
            'message' => 'Candidature annulée avec succès.',
        ]);
    }
}
