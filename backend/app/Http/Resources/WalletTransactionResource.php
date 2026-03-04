<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'amount'      => $this->amount,
            'reference'   => $this->reference,
            'description' => $this->description,
            'booking_id'  => $this->booking_id,
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
