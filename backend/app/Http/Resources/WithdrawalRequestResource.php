<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'amount' => (int) $this->amount,
            'payment_mode' => $this->payment_mode,
            'phone_number' => $this->phone_number,
            'phone_country' => $this->phone_country,
            'status' => $this->status,
            'notes' => $this->notes,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
