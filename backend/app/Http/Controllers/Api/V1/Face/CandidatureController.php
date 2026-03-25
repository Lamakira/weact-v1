<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\StoreCandidatureRequest;
use App\Http\Resources\CandidatureResource;
use App\Http\Resources\FaceCandidatureResource;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

        // Update status to confirmed
        $candidature->status = CandidatureStatus::Confirmed;
        $candidature->save();

        // Notify the producer that this face confirmed
        $mission = $candidature->mission;
        $producerUser = User::where('userable_type', \App\Models\Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->first();

        if ($producerUser) {
            Notification::create([
                'user_id' => $producerUser->id,
                'type'    => 'face_confirmed_participation',
                'data'    => [
                    'message'    => $face->prenom . ' a confirmé sa participation à la mission "' . $mission->titre . '".',
                    'mission_id' => $mission->id,
                    'url'        => "/producer/missions/{$mission->id}/candidatures",
                ],
            ]);
        }

        // Check if all selected faces have now confirmed → move candidatures to in_progress
        $missionPayment = $mission->payment;
        if ($missionPayment) {
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
            return response()->json([
                'message' => 'Seules les candidatures en attente peuvent être annulées.',
            ], 400);
        }

        $candidature->update(['status' => CandidatureStatus::Cancelled]);

        return response()->json([
            'message' => 'Candidature annulée avec succès.',
        ]);
    }
}
