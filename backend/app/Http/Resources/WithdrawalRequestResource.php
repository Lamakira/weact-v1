<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
        /** @var Carbon|null $processedAt */
        $processedAt = $this->processed_at;
        /** @var Carbon|null $createdAt */
        $createdAt = $this->created_at;

        return [
            'id' => $this->uuid,
            'amount' => (int) $this->amount,
            'payment_mode' => $this->payment_mode,
            'phone_number' => $this->phone_number,
            'phone_country' => $this->phone_country,
            'status' => $this->status,
            'notes' => $this->notes,
            'processed_at' => $processedAt?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
