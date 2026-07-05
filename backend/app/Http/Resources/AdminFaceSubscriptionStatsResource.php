<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFaceSubscriptionStatsResource extends JsonResource
{
    /**
     * The resource wraps an associative array of subscription KPIs.
     *
     * @var array<string, mixed>
     */
    public $resource;

    /**
     * Transform the subscription back-office KPIs into a structured response.
     *
     * Revenue figures follow decision D-1: SUM(paid_amount) dated by paid_at,
     * independent of the current status; manual admin activations
     * (paid_amount IS NULL) are excluded by construction.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'active_by_plan' => [
                'starter' => $this->resource['active_starter'] ?? 0,
                'pro' => $this->resource['active_pro'] ?? 0,
                'elite' => $this->resource['active_elite'] ?? 0,
                'total' => $this->resource['active_total'] ?? 0,
            ],
            'revenue' => [
                'current_month' => $this->resource['revenue_current_month'] ?? 0,
                'total' => $this->resource['revenue_total'] ?? 0,
                'currency' => $this->resource['currency'] ?? 'XOF',
            ],
            'expiring_within_30_days' => $this->resource['expiring_within_30_days'] ?? 0,
            'pending_payment_count' => $this->resource['pending_payment_count'] ?? 0,
            'failed_count' => $this->resource['failed_count'] ?? 0,
        ];
    }
}
