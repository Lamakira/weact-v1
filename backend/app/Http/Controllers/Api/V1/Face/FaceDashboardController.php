<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChartStatsResource;
use App\Http\Resources\FaceDashboardStatsResource;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated Face.
     *
     * Returns candidature counts grouped by status:
     * - pending: Candidatures waiting for producer response
     * - accepted: Candidatures accepted, waiting for Face confirmation
     * - in_progress: Confirmed candidatures (active missions)
     * - completed: Finished missions
     *
     * Note: Rejected candidatures are excluded from the stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        // Get candidature counts by status
        $statusCounts = Candidature::where('face_id', $face->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => new FaceDashboardStatsResource($statusCounts),
            'message' => 'Dashboard stats retrieved successfully',
        ]);
    }

    /**
     * Get chart statistics for the authenticated Face.
     *
     * Returns aggregated data for the last 6 months:
     * - candidatures_by_month: Candidatures grouped by month and status
     * - missions_completed_by_month: Completed missions grouped by month
     *
     * (FR53 - Dashboard Face Charts)
     */
    public function chartStats(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();

        // Get candidatures grouped by month and status
        $candidaturesByMonth = $this->getCandidaturesByMonth($face->id, $sixMonthsAgo);

        // Get completed missions grouped by month
        $missionsCompletedByMonth = $this->getMissionsCompletedByMonth($face->id, $sixMonthsAgo);

        return response()->json([
            'data' => new ChartStatsResource([
                'candidatures_by_month' => $candidaturesByMonth,
                'missions_completed_by_month' => $missionsCompletedByMonth,
            ]),
            'message' => 'Chart stats retrieved successfully',
        ]);
    }

    /**
     * Get candidatures grouped by month and status for the last 6 months.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCandidaturesByMonth(int $faceId, Carbon $since): array
    {
        // Query candidatures grouped by month and status
        $rawData = Candidature::where('face_id', $faceId)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, status, COUNT(*) as count")
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get();

        // Generate all months in the range
        $months = $this->generateMonthsRange($since);

        // Initialize result with zero counts for all statuses
        $result = [];
        foreach ($months as $month) {
            $result[$month] = [
                'month' => $month,
                'pending' => 0,
                'accepted' => 0,
                'confirmed' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'rejected' => 0,
            ];
        }

        // Fill in actual counts from query
        foreach ($rawData as $row) {
            $month = $row->month;
            $status = $row->status->value ?? $row->status;

            if (isset($result[$month])) {
                $result[$month][$status] = (int) $row->count;
            }
        }

        return array_values($result);
    }

    /**
     * Get completed missions grouped by month for the last 6 months.
     *
     * Uses updated_at to track when the candidature was marked as completed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMissionsCompletedByMonth(int $faceId, Carbon $since): array
    {
        // Query completed candidatures grouped by month
        $rawData = Candidature::where('face_id', $faceId)
            ->where('status', CandidatureStatus::Completed)
            ->where('updated_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Generate all months in the range
        $months = $this->generateMonthsRange($since);

        // Build result with zero counts for missing months
        $result = [];
        foreach ($months as $month) {
            $result[] = [
                'month' => $month,
                'count' => isset($rawData[$month]) ? (int) $rawData[$month]->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Generate array of month strings from since date to current month.
     *
     * @return array<int, string>
     */
    private function generateMonthsRange(Carbon $since): array
    {
        $months = [];
        $current = $since->copy();
        $end = Carbon::now()->endOfMonth();

        while ($current <= $end) {
            $months[] = $current->format('Y-m');
            $current->addMonth();
        }

        return $months;
    }

    /**
     * Get the count of available missions on the platform.
     *
     * Returns count of missions that are:
     * - Published (status = 'published')
     * - Candidature deadline not passed (date_limite_candidature >= today)
     *
     * (FR54 - Dashboard Face Quick Access to Missions)
     */
    public function availableMissionsCount(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        // Count available missions (published, candidature deadline not passed)
        $count = Mission::where('status', MissionStatus::Published)
            ->where('date_limite_candidature', '>=', Carbon::today())
            ->count();

        return response()->json([
            'data' => [
                'count' => $count,
            ],
            'message' => 'Available missions count retrieved successfully',
        ]);
    }

    /**
     * Get the authenticated Face from the request.
     */
    private function getAuthenticatedFace(Request $request): Face|JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        return Face::findOrFail($user->userable_id);
    }
}
