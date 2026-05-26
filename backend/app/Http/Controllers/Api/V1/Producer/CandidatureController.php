<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\CandidatureStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\IndexMissionCandidaturesRequest;
use App\Http\Resources\CandidatureResource;
use App\Http\Resources\ProducerCandidatureResource;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidatureController extends Controller
{
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
            ->with(['face.activeSubscription', 'conversation'])
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

        return response()->json([
            'data' => new CandidatureResource($candidature),
            'message' => 'Candidature refusée',
        ]);
    }
}
