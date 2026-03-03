<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Credit a user's wallet balance.
     * MUST be called inside an existing DB::transaction().
     */
    public function credit(int $userId, int $amount, Booking $booking, string $description): WalletTransaction
    {
        User::where('id', $userId)->lockForUpdate()->increment('balance', $amount);

        return WalletTransaction::create([
            'user_id' => $userId,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => $amount,
            'reference' => 'wlt_' . Str::uuid()->toString(),
            'description' => $description,
        ]);
    }
}
