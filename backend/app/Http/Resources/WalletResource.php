<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{balance: int, pending_escrow: int, transactions: LengthAwarePaginator, withdrawal_requests: Collection<int, \App\Models\WithdrawalRequest>} $data */
        $data = $this->resource;

        return [
            'balance' => $data['balance'],
            'pending_escrow' => $data['pending_escrow'],
            'withdrawal_mode' => config('app.withdrawal_mode', 'manual'),
            'withdrawal_requests' => WithdrawalRequestResource::collection($data['withdrawal_requests']),
            'transactions' => WalletTransactionResource::collection($data['transactions']),
            'transactions_meta' => [
                'current_page' => $data['transactions']->currentPage(),
                'last_page' => $data['transactions']->lastPage(),
                'per_page' => $data['transactions']->perPage(),
                'total' => $data['transactions']->total(),
            ],
        ];
    }
}
