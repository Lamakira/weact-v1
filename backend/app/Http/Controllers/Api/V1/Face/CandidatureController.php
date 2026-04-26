<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\CandidatureStatus;
use App\Enums\ErrorCodes;
use App\Enums\MissionGender;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CandidatureController extends Controller
{
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
            ->with(['mission', 'mission.producer', 'conversation'])
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

        // Verify candidature is pending
        if ($candidature->status !== CandidatureStatus::Pending) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Seules les candidatures en attente peuvent être annulées.'),
                400
            );
        }

        $candidature->update(['status' => CandidatureStatus::Cancelled]);

        return response()->json([
            'message' => 'Candidature annulée avec succès.',
        ]);
    }
}
