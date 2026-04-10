<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Article;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class AdminDashboardService
{
    /**
     * @var list<array{type: string, title: string, description: string, timestamp: string, link: string|null}>
     */
    private array $activities = [];

    /**
     * Get time-windowed trend KPIs for the admin dashboard.
     *
     * @return array<string, array<string, int|float>>
     */
    public function getTrends(): array
    {
        $now = Carbon::now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        return [
            'registrations' => [
                'faces_7d' => Face::where('created_at', '>=', $sevenDaysAgo)->count(),
                'faces_30d' => Face::where('created_at', '>=', $thirtyDaysAgo)->count(),
                'producers_7d' => Producer::where('created_at', '>=', $sevenDaysAgo)->count(),
                'producers_30d' => Producer::where('created_at', '>=', $thirtyDaysAgo)->count(),
            ],
            'missions' => [
                'published_7d' => Mission::where('status', MissionStatus::Published)
                    ->where('created_at', '>=', $sevenDaysAgo)->count(),
                'published_30d' => Mission::where('status', MissionStatus::Published)
                    ->where('created_at', '>=', $thirtyDaysAgo)->count(),
                'completed_7d' => Mission::where('status', MissionStatus::Completed)
                    ->where('created_at', '>=', $sevenDaysAgo)->count(),
                'completed_30d' => Mission::where('status', MissionStatus::Completed)
                    ->where('created_at', '>=', $thirtyDaysAgo)->count(),
            ],
            'candidatures' => [
                'submitted_7d' => Candidature::where('created_at', '>=', $sevenDaysAgo)->count(),
                'submitted_30d' => Candidature::where('created_at', '>=', $thirtyDaysAgo)->count(),
                'acceptance_rate_30d' => $this->calculateAcceptanceRate($thirtyDaysAgo),
            ],
            'health' => [
                'missions_without_candidatures_30d' => $this->countMissionsWithoutCandidatures($thirtyDaysAgo),
                'active_users_7d' => $this->countActiveUsers($sevenDaysAgo),
            ],
        ];
    }

    /**
     * Get the 5 most recent platform events for the activity feed.
     *
     * Note: Fetches 5 items from each source (missions, articles, users) then merges
     * and sorts in PHP. Consider a UNION query or dedicated activity log table if
     * the number of event sources grows significantly.
     *
     * @return array<int, array<string, string|null>>
     */
    public function getRecentActivity(): array
    {
        $this->activities = [];

        // Latest missions created
        $recentMissions = Mission::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'titre', 'status', 'created_at']);

        foreach ($recentMissions as $mission) {
            $this->activities[] = [
                'type' => 'mission',
                'title' => $mission->titre,
                'description' => 'Mission '.$mission->status->label(),
                'timestamp' => $mission->created_at->toISOString(),
                'link' => '/admin/missions/'.$mission->id,
            ];
        }

        // Latest articles published
        $recentArticles = Article::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'status', 'created_at']);

        foreach ($recentArticles as $article) {
            $this->activities[] = [
                'type' => 'article',
                'title' => $article->title,
                'description' => 'Article '.$article->status->label(),
                'timestamp' => $article->created_at->toISOString(),
                'link' => '/admin/articles/'.$article->id.'/edit',
            ];
        }

        // Latest user registrations
        $recentUsers = User::with('userable')
            ->whereNotNull('userable_type')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'email', 'userable_type', 'userable_id', 'created_at']);

        foreach ($recentUsers as $user) {
            $type = str_contains($user->userable_type, 'Face') ? 'Face' : 'Producteur';
            $name = (string) data_get($user, 'userable.display_name', $user->email);
            $this->activities[] = [
                'type' => 'user',
                'title' => $name,
                'description' => "Inscription $type",
                'timestamp' => $user->created_at->toISOString(),
                'link' => str_contains($user->userable_type, 'Face')
                    ? '/admin/faces/'.$user->userable_id
                    : '/admin/producers/'.$user->userable_id,
            ];
        }

        // Sort all by timestamp desc, take top 5
        usort(
            $this->activities,
            fn (array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp'])
        );

        return array_slice($this->activities, 0, 5);
    }

    /**
     * Calculate candidature acceptance rate for a given period.
     */
    private function calculateAcceptanceRate(Carbon $since): float
    {
        $total = Candidature::where('created_at', '>=', $since)->count();

        if ($total === 0) {
            return 0.0;
        }

        $accepted = Candidature::where('created_at', '>=', $since)
            ->whereIn('status', [
                CandidatureStatus::Accepted,
                CandidatureStatus::Confirmed,
                CandidatureStatus::InProgress,
                CandidatureStatus::Completed,
            ])
            ->count();

        return round(($accepted / $total) * 100, 1);
    }

    /**
     * Count published missions with zero candidatures in the given period.
     */
    private function countMissionsWithoutCandidatures(Carbon $since): int
    {
        return Mission::where('status', MissionStatus::Published)
            ->where('created_at', '>=', $since)
            ->doesntHave('candidatures')
            ->count();
    }

    /**
     * Count users with active Sanctum tokens used in the given period.
     */
    private function countActiveUsers(Carbon $since): int
    {
        return PersonalAccessToken::where('last_used_at', '>=', $since)
            ->distinct('tokenable_id')
            ->count('tokenable_id');
    }
}
