<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FaceSubscription
 */
class AdminFaceSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'plan' => $this->plan?->value,
            'plan_label' => $this->plan?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'paid_amount' => $this->paid_amount,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'audits' => AdminFaceSubscriptionAuditResource::collection(
                $this->whenLoaded('audits'),
            ),
        ];
    }
}
