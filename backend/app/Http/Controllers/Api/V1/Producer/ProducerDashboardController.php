<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\CandidatureStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProducerDashboardStatsResource;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProducerDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated Producer.
     *
     * Returns mission counts grouped by status:
     * - published: Active missions accepting candidatures
     * - in_progress: Missions with accepted/confirmed candidatures (closed status with active work)
     * - closed: Missions no longer accepting candidatures
     * - completed: Finished missions
     *
     * Note: Draft missions are excluded from the stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedProducer($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $producer = $result;

        // Get mission counts by status
        $statusCounts = Mission::where('producer_id', $producer->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Count missions with active work (confirmed or in_progress candidatures)
        // This represents "En cours" - missions being actively executed
        $inProgressCount = Mission::where('producer_id', $producer->id)
            ->whereHas('candidatures', function ($query) {
                $query->whereIn('status', [
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                ]);
            })
            ->count();

        // Count total candidatures received across all Producer's missions (FR56)
        // Includes ALL statuses: pending, accepted, confirmed, in_progress, completed, rejected
        $totalCandidatures = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })->count();

        // Count unique Faces the producer has worked with (FR57)
        // Only counts completed candidatures - represents actual collaborations
        $uniqueCollaborators = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })
            ->where('status', CandidatureStatus::Completed->value)
            ->distinct()
            ->count('face_id');

        // Get Producer's average rating and ratings count (FR58)
        // Uses existing model accessors for rating calculations
        $averageRating = $producer->average_rating;
        $ratingsCount = $producer->ratings_count;

        // Calculate acceptance rate (FR59)
        // Accepted = candidatures that moved past pending (accepted, confirmed, in_progress, completed)
        $acceptedStatuses = [
            CandidatureStatus::Accepted->value,
            CandidatureStatus::Confirmed->value,
            CandidatureStatus::InProgress->value,
            CandidatureStatus::Completed->value,
        ];

        $acceptedCount = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })->whereIn('status', $acceptedStatuses)->count();

        $acceptanceRate = $totalCandidatures > 0
            ? round(($acceptedCount / $totalCandidatures) * 100, 1)
            : 0.0;

        // Calculate average response time in hours (FR59)
        // Response time = time from candidature creation to decision (accepted or rejected)
        $decidedCandidatures = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })
            ->whereIn('status', [CandidatureStatus::Accepted->value, CandidatureStatus::Rejected->value])
            ->whereColumn('updated_at', '!=', 'created_at')
            ->get(['created_at', 'updated_at']);

        $averageResponseTimeHours = null;
        if ($decidedCandidatures->isNotEmpty()) {
            // Use absolute diff to handle any timestamp ordering issues
            $totalMinutes = $decidedCandidatures->sum(fn ($c) => abs($c->updated_at->diffInMinutes($c->created_at)));
            $averageResponseTimeHours = round(($totalMinutes / 60) / $decidedCandidatures->count(), 1);
        }

        return response()->json([
            'data' => new ProducerDashboardStatsResource(
                $statusCounts,
                $inProgressCount,
                $totalCandidatures,
                $uniqueCollaborators,
                $averageRating,
                $ratingsCount,
                $acceptanceRate,
                $averageResponseTimeHours
            ),
            'message' => 'Dashboard stats retrieved successfully',
        ]);
    }

    /**
     * Get the authenticated Producer from the request.
     */
    private function getAuthenticatedProducer(Request $request): Producer|JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Producer::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Producteurs',
                ],
            ], 403);
        }

        return Producer::findOrFail($user->userable_id);
    }
}
