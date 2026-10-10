<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only data for the Producer dashboard modules.
 */
class ProducerDashboardService
{
    /**
     * Statuses counting as « work under way » for a Face on a mission
     * (same family as the « En cours » KPI, plus completed ones).
     *
     * @var array<int, string>
     */
    private const CONFIRMED_STATUSES = [
        CandidatureStatus::Confirmed->value,
        CandidatureStatus::InProgress->value,
        CandidatureStatus::Completed->value,
    ];

    /**
     * Active missions: published, or closed with confirmed / in-progress
     * candidatures (the « En cours » KPI definition). Two queries, whatever the volume.
     *
     * @return array{missions: Collection<int, Mission>, total: int}
     */
    public function activeMissions(Producer $producer, int $limit): array
    {
        $since = now()->subDay();

        $missions = $this->activeQuery($producer)
            ->withCount([
                'candidatures',
                'candidatures as new_candidatures_count' => fn (Builder $q) => $q->where('created_at', '>=', $since),
                'candidatures as confirmed_count' => fn (Builder $q) => $q->whereIn('status', self::CONFIRMED_STATUSES),
            ])
            ->orderByRaw('status = ? desc', [MissionStatus::Published->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'missions' => $missions,
            'total' => $this->activeQuery($producer)->count(),
        ];
    }

    /**
     * @return Builder<Mission>
     */
    private function activeQuery(Producer $producer): Builder
    {
        return Mission::query()
            ->where('producer_id', $producer->id)
            ->where(function (Builder $q): void {
                $q->where('status', MissionStatus::Published->value)
                    ->orWhere(function (Builder $q): void {
                        $q->whereIn('status', [
                            MissionStatus::Closed->value,
                            MissionStatus::PendingAttendanceValidation->value,
                        ])->whereHas('candidatures', fn (Builder $c) => $c->whereIn('status', [
                            CandidatureStatus::Confirmed->value,
                            CandidatureStatus::InProgress->value,
                        ]));
                    });
            });
    }
}
