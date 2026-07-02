<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Face-facing (owner) view of one of the Face's own subscription rows.
 *
 * Deliberately a narrower projection than {@see AdminFaceSubscriptionResource}:
 * it omits the admin audit trail (admin identities + internal notes) which is
 * privileged operator data. It exposes only the billing-relevant fields the
 * Face needs to render their own "Facturation & Abonnement" history.
 *
 * @mixin \App\Models\FaceSubscription
 */
class FaceSubscriptionHistoryResource extends JsonResource
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
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
