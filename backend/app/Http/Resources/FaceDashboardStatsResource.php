<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CandidatureStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaceDashboardStatsResource extends JsonResource
{
    /**
     * The resource wraps an array of status => count mappings.
     *
     * @var array<string, int>
     */
    public $resource;

    /**
     * Transform the status counts into a structured response.
     *
     * Groups 'confirmed' and 'in_progress' together as 'in_progress' for the UI
     * since both represent active missions from the Face's perspective.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        // Get counts with defaults to 0
        $pending = $this->resource[CandidatureStatus::Pending->value] ?? 0;
        $accepted = $this->resource[CandidatureStatus::Accepted->value] ?? 0;
        $confirmed = $this->resource[CandidatureStatus::Confirmed->value] ?? 0;
        $inProgress = $this->resource[CandidatureStatus::InProgress->value] ?? 0;
        $completed = $this->resource[CandidatureStatus::Completed->value] ?? 0;

        // Note: 'rejected' is intentionally excluded from stats

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'in_progress' => $confirmed + $inProgress, // Group active missions together
            'completed' => $completed,
        ];
    }
}
