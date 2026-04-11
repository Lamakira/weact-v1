<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use RuntimeException;

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
            'reference' => 'wlt_'.Str::uuid()->toString(),
            'description' => $description,
        ]);
    }

    /**
     * Credit a user's wallet for a mission payment (no Booking reference).
     * MUST be called inside an existing DB::transaction().
     */
    public function creditDirect(int $userId, int $amount, string $description): WalletTransaction
    {
        User::where('id', $userId)->lockForUpdate()->increment('balance', $amount);

        return WalletTransaction::create([
            'user_id' => $userId,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => $amount,
            'reference' => 'wlt_'.Str::uuid()->toString(),
            'description' => $description,
        ]);
    }

    /**
     * Debit a user's wallet balance for a withdrawal.
     * MUST be called inside an existing DB::transaction().
     *
     * @throws RuntimeException if balance is insufficient
     */
    public function debit(
        int $userId,
        int $amount,
        string $description,
        ?int $bookingId = null,
        string $status = 'pending',
    ): WalletTransaction {
        // Atomic check + decrement — lockForUpdate prevents race conditions (double-spend)
        $updated = User::where('id', $userId)
            ->where('balance', '>=', $amount)
            ->lockForUpdate()
            ->decrement('balance', $amount);

        if ($updated === 0) {
            throw new RuntimeException('Insufficient balance');
        }

        return WalletTransaction::create([
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'type' => 'debit',
            'amount' => $amount,
            'reference' => 'wlt_'.Str::uuid()->toString(),
            'description' => $description,
            'status' => $status,
        ]);
    }
}
