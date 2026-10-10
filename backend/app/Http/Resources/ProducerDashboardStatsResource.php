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
     * Producer's average rating score (1-5 scale, nullable if no ratings).
     */
    private ?float $averageRating;

    /**
     * Total number of ratings received by the Producer.
     */
    private int $ratingsCount;

    /**
     * Producer's candidature acceptance rate (0-100 percentage, FR59).
     */
    private float $acceptanceRate;

    /**
     * Average response time in hours for candidature decisions (FR59).
     */
    private ?float $averageResponseTimeHours;

    /**
     * Ratings count per score, 5 down to 1 (same sources as the average).
     *
     * @var array<int, int>
     */
    private array $ratingDistribution;

    /**
     * Create a new resource instance.
     *
     * @param  array<string, int>  $statusCounts  Mission counts by status
     * @param  int  $inProgressCount  Count of missions with active candidatures
     * @param  int  $totalCandidatures  Total candidatures received (FR56)
     * @param  int  $uniqueCollaborators  Unique Faces worked with (FR57)
     * @param  float|null  $averageRating  Producer's average rating (FR58)
     * @param  int  $ratingsCount  Total ratings received (FR58)
     * @param  float  $acceptanceRate  Candidature acceptance rate percentage (FR59)
     * @param  float|null  $averageResponseTimeHours  Average response time in hours (FR59)
     * @param  array<int, int>  $ratingDistribution  Ratings count per score, 5 down to 1
     */
    public function __construct(
        array $statusCounts,
        int $inProgressCount = 0,
        int $totalCandidatures = 0,
        int $uniqueCollaborators = 0,
        ?float $averageRating = null,
        int $ratingsCount = 0,
        float $acceptanceRate = 0.0,
        ?float $averageResponseTimeHours = null,
        array $ratingDistribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
    ) {
        parent::__construct($statusCounts);
        $this->inProgressCount = $inProgressCount;
        $this->totalCandidatures = $totalCandidatures;
        $this->uniqueCollaborators = $uniqueCollaborators;
        $this->averageRating = $averageRating;
        $this->ratingsCount = $ratingsCount;
        $this->acceptanceRate = $acceptanceRate;
        $this->averageResponseTimeHours = $averageResponseTimeHours;
        $this->ratingDistribution = $ratingDistribution;
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
     * @return array<string, int|float|object|null>
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
            'average_rating' => $this->averageRating,
            'ratings_count' => $this->ratingsCount,
            'acceptance_rate' => $this->acceptanceRate,
            'average_response_time_hours' => $this->averageResponseTimeHours,
            // Ratings count per score {5..1}; sums to ratings_count
            'rating_distribution' => (object) $this->ratingDistribution,
            // AC #5 requires this explicit field name (alias for 'completed')
            'completed_missions_count' => $completed,
        ];
    }
}
