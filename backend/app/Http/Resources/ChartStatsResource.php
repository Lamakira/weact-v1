<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for chart statistics data.
 *
 * Transforms raw aggregated data into chart-friendly format
 * for the Face dashboard charts feature (FR53).
 */
class ChartStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'candidatures_by_month' => $this->resource['candidatures_by_month'] ?? [],
            'missions_completed_by_month' => $this->resource['missions_completed_by_month'] ?? [],
        ];
    }
}
