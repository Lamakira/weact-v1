<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WalletService
{
    /**
     * Credit a user's wallet balance.
     *
     * Atomic by itself: the balance update and the ledger row are all-or-nothing
     * (own DB::transaction(), a savepoint when nested). Callers still need their
     * own transaction when the wallet move must be atomic with THEIR other writes.
     */
    public function credit(int $userId, int $amount, Booking $booking, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $booking, $description): WalletTransaction {
            User::where('id', $userId)->lockForUpdate()->increment('balance', $amount);

            return WalletTransaction::create([
                'user_id' => $userId,
                'booking_id' => $booking->id,
                'type' => 'credit',
                'amount' => $amount,
                'reference' => 'wlt_'.Str::uuid()->toString(),
                'description' => $description,
            ]);
        });
    }

    /**
     * Credit a user's wallet for a mission payment (no Booking reference).
     *
     * Atomic by itself (balance update + ledger row); callers still need their
     * own transaction when the wallet move must be atomic with THEIR other writes.
     */
    public function creditDirect(int $userId, int $amount, string $description): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amount, $description): WalletTransaction {
            User::where('id', $userId)->lockForUpdate()->increment('balance', $amount);

            return WalletTransaction::create([
                'user_id' => $userId,
                'booking_id' => null,
                'type' => 'credit',
                'amount' => $amount,
                'reference' => 'wlt_'.Str::uuid()->toString(),
                'description' => $description,
            ]);
        });
    }

    /**
     * Debit a user's wallet balance for a withdrawal.
     *
     * Atomic by itself (balance check + decrement + ledger row); callers still
     * need their own transaction when the debit must be atomic with THEIR other writes.
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
        return DB::transaction(function () use ($userId, $amount, $description, $bookingId, $status): WalletTransaction {
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
        });
    }
}
