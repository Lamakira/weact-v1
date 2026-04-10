<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ArticleStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminDashboardStatsResource;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService
    ) {}

    /**
     * Get global platform KPIs for the admin dashboard.
     *
     * Returns counts for: users (faces, producers, admins),
     * missions (by status), articles (by status), candidatures (total + completed).
     *
     * (FR68 - Admin Dashboard KPIs)
     */
    public function stats(): JsonResponse
    {
        // User counts
        $facesCount = Face::count();
        $producersCount = Producer::count();
        $adminsCount = Admin::count();

        // Mission counts by status
        $missionCounts = Mission::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Article counts by status
        $articleCounts = Article::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Candidature counts
        $candidaturesTotal = Candidature::count();
        $candidaturesCompleted = Candidature::where('status', CandidatureStatus::Completed)->count();

        $stats = [
            'faces_count' => $facesCount,
            'producers_count' => $producersCount,
            'admins_count' => $adminsCount,
            'missions_published' => $missionCounts[MissionStatus::Published->value] ?? 0,
            'missions_closed' => $missionCounts[MissionStatus::Closed->value] ?? 0,
            'missions_completed' => $missionCounts[MissionStatus::Completed->value] ?? 0,
            'missions_draft' => $missionCounts[MissionStatus::Draft->value] ?? 0,
            'articles_published' => $articleCounts[ArticleStatus::Published->value] ?? 0,
            'articles_draft' => $articleCounts[ArticleStatus::Draft->value] ?? 0,
            'candidatures_total' => $candidaturesTotal,
            'candidatures_completed' => $candidaturesCompleted,
        ];

        return response()->json([
            'data' => new AdminDashboardStatsResource($stats),
            'message' => 'Admin dashboard stats retrieved successfully',
        ]);
    }

    /**
     * Get time-windowed trend KPIs for the admin dashboard.
     */
    public function trends(): JsonResponse
    {
        $trends = $this->dashboardService->getTrends();

        return response()->json([
            'data' => $trends,
            'message' => 'Dashboard trends retrieved successfully',
        ]);
    }

    /**
     * Get the 5 most recent platform events.
     */
    public function recentActivity(): JsonResponse
    {
        $activities = $this->dashboardService->getRecentActivity();

        return response()->json([
            'data' => $activities,
            'message' => 'Recent activity retrieved successfully',
        ]);
    }
}
