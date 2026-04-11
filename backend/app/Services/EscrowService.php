<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\EscrowTransaction;

class EscrowService
{
    use RecordsFinancialEvent;

    /**
     * Lock escrow for a paid booking.
     * MUST be called inside an existing DB::transaction().
     */
    public function lock(Booking $booking): EscrowTransaction
    {
        $escrow = EscrowTransaction::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'amount' => $booking->montant_face_recoit,
                'status' => EscrowStatus::Locked->value,
                'locked_at' => now(),
            ]
        );

        if (! $escrow->wasRecentlyCreated) {
            return $escrow;
        }

        $this->recordFinancialEvent(
            FinancialEventType::EscrowLock,
            $booking,
            $booking->montant_face_recoit,
        );

        return $escrow;
    }

    /**
     * Release escrow and credit the Face's wallet.
     * MUST be called inside an existing DB::transaction().
     */
    public function release(Booking $booking, WalletService $walletService): void
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = $booking->escrowTransaction()->lockForUpdate()->first();

        // Guard: no escrow record means nothing to release
        if ($escrow === null) {
            return;
        }

        // Idempotent: already released
        if (in_array($escrow->status, [
            EscrowStatus::Released->value,
            EscrowStatus::Refunded->value,
            EscrowStatus::Pending->value,
        ], true)) {
            return;
        }

        $escrow->update([
            'status' => EscrowStatus::Released->value,
            'released_at' => now(),
        ]);

        $walletService->credit(
            userId: $booking->face_id,
            amount: $booking->montant_face_recoit,
            booking: $booking,
            description: "Booking : escrow libéré",
        );

        $this->recordFinancialEvent(
            FinancialEventType::EscrowRelease,
            $booking,
            $booking->montant_face_recoit,
        );
    }

    /**
     * Mark escrow as refunded for a no-show (wallet credit, no FedaPay call).
     * MUST be called inside an existing DB::transaction().
     *
     * @throws \RuntimeException if escrow is missing or already settled
     */
    public function markRefundedForNoShow(Booking $booking): void
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = EscrowTransaction::query()
            ->where('booking_id', $booking->id)
            ->lockForUpdate()
            ->first();

        if ($escrow === null) {
            throw new \RuntimeException("Missing escrow transaction for paid booking {$booking->id}.");
        }

        if (in_array($escrow->status, [
            EscrowStatus::Released->value,
            EscrowStatus::Refunded->value,
        ], true)) {
            throw new \RuntimeException("Escrow already settled for booking {$booking->id}.");
        }

        $escrow->update([
            'status' => EscrowStatus::Refunded->value,
            'refunded_at' => now(),
        ]);
    }

    /**
     * Process a refund for a cancelled paid booking by crediting the Producer wallet.
     * MUST be called inside an existing DB::transaction().
     */
    public function refund(Booking $booking, WalletService $walletService): void
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = $booking->escrowTransaction()->lockForUpdate()->first();

        // Guard: no escrow record means nothing to refund.
        if ($escrow === null) {
            return;
        }

        // Idempotent: already refunded.
        if (in_array($escrow->status, [
            EscrowStatus::Refunded->value,
            EscrowStatus::Pending->value,
        ], true)) {
            return;
        }

        $refundAmount = (int) round($booking->montant_total_producteur * 0.90);
        $retainedAmount = $booking->montant_total_producteur - $refundAmount;

        $escrow->update([
            'status' => EscrowStatus::Refunded->value,
            'fedapay_ref' => null,
            'refunded_at' => now(),
        ]);

        $walletService->credit(
            userId: $booking->producer_id,
            amount: $refundAmount,
            booking: $booking,
            description: "Booking : remboursement annulation (90%)",
        );

        $this->recordFinancialEvent(
            FinancialEventType::Refund,
            $booking,
            $refundAmount,
            [
                'status' => 'completed',
                'metadata' => [
                    'refund_channel' => 'wallet',
                    'refund_percentage' => 90,
                    'retained_amount' => $retainedAmount,
                ],
            ],
        );
    }
}
