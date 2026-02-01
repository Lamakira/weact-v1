<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MissionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProducerDashboardStatsResource extends JsonResource
{
    /**
     * The resource wraps an array of status => count mappings.
     *
     * @var array<string, int>
     */
    public $resource;

    /**
     * Count of missions with active candidatures (confirmed/in_progress).
     */
    private int $inProgressCount;

    /**
     * Total number of candidatures received across all missions.
     */
    private int $totalCandidatures;

    /**
     * Count of unique Faces the producer has worked with (completed candidatures).
     */
    private int $uniqueCollaborators;

    /**
     * Create a new resource instance.
     *
     * @param  array<string, int>  $statusCounts  Mission counts by status
     * @param  int  $inProgressCount  Count of missions with active candidatures
     * @param  int  $totalCandidatures  Total candidatures received (FR56)
     * @param  int  $uniqueCollaborators  Unique Faces worked with (FR57)
     */
    public function __construct(array $statusCounts, int $inProgressCount = 0, int $totalCandidatures = 0, int $uniqueCollaborators = 0)
    {
        parent::__construct($statusCounts);
        $this->inProgressCount = $inProgressCount;
        $this->totalCandidatures = $totalCandidatures;
        $this->uniqueCollaborators = $uniqueCollaborators;
    }

    /**
     * Transform the status counts into a structured response.
     *
     * Mapping:
     * - published: Missions accepting candidatures
     * - in_progress: Missions with confirmed/in_progress candidatures (active work)
     * - closed: Missions no longer accepting candidatures
     * - completed: Finished missions
     *
     * Note: 'draft' missions are intentionally excluded from stats.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        // Get counts with defaults to 0
        $published = $this->resource[MissionStatus::Published->value] ?? 0;
        $closed = $this->resource[MissionStatus::Closed->value] ?? 0;
        $completed = $this->resource[MissionStatus::Completed->value] ?? 0;

        // Note: 'draft' is intentionally excluded from stats

        return [
            'published' => $published,
            'in_progress' => $this->inProgressCount,
            'closed' => $closed,
            'completed' => $completed,
            'total_candidatures' => $this->totalCandidatures,
            'unique_collaborators' => $this->uniqueCollaborators,
        ];
    }
}
