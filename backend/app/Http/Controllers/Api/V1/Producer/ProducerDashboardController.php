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

        return response()->json([
            'data' => new ProducerDashboardStatsResource($statusCounts, $inProgressCount),
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
