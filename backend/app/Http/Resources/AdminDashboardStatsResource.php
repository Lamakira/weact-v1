<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardStatsResource extends JsonResource
{
    /**
     * The resource wraps an associative array of platform-wide stats.
     *
     * @var array<string, mixed>
     */
    public $resource;

    /**
     * Transform the platform stats into a structured response.
     *
     * @return array<string, array<string, int>>
     */
    public function toArray(Request $request): array
    {
        return [
            'users' => [
                'faces' => $this->resource['faces_count'] ?? 0,
                'producers' => $this->resource['producers_count'] ?? 0,
                'admins' => $this->resource['admins_count'] ?? 0,
            ],
            'missions' => [
                'published' => $this->resource['missions_published'] ?? 0,
                'closed' => $this->resource['missions_closed'] ?? 0,
                'completed' => $this->resource['missions_completed'] ?? 0,
                'draft' => $this->resource['missions_draft'] ?? 0,
            ],
            'articles' => [
                'published' => $this->resource['articles_published'] ?? 0,
                'draft' => $this->resource['articles_draft'] ?? 0,
            ],
            'candidatures' => [
                'total' => $this->resource['candidatures_total'] ?? 0,
                'completed' => $this->resource['candidatures_completed'] ?? 0,
            ],
        ];
    }
}
