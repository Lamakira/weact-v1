<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
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
        $escrow = EscrowTransaction::create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => 'locked',
            'locked_at' => now(),
        ]);

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
        if ($escrow->status === 'released') {
            return;
        }

        $escrow->update([
            'status' => 'released',
            'released_at' => now(),
        ]);

        $walletService->credit(
            userId: $booking->face_id,
            amount: $booking->montant_face_recoit,
            booking: $booking,
            description: "Booking #{$booking->id} — escrow libéré",
        );

        $this->recordFinancialEvent(
            FinancialEventType::EscrowRelease,
            $booking,
            $booking->montant_face_recoit,
        );
    }

    /**
     * Process a refund for a cancelled paid booking.
     * MUST be called inside an existing DB::transaction().
     */
    public function refund(Booking $booking, FedapayService $fedapayService): void
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = $booking->escrowTransaction()->lockForUpdate()->first();

        // Guard: no escrow record means nothing to refund.
        if ($escrow === null) {
            return;
        }

        // Idempotent: already refunded.
        if ($escrow->status === 'refunded') {
            return;
        }

        $refundAmount = (int) round($booking->montant_total_producteur * 0.85);
        $retainedAmount = $booking->montant_total_producteur - $refundAmount;
        $idempotencyKey = "refund-booking-{$booking->id}";
        $refund = $fedapayService->initiateRefund($booking, $refundAmount, $idempotencyKey);
        $refundId = isset($refund['fedapay_refund_id']) ? (string) $refund['fedapay_refund_id'] : null;
        $refundStatus = $refund['status'] ?? 'pending';

        $escrow->update([
            'status' => 'refunded',
            'fedapay_ref' => $refundId,
            'refunded_at' => now(),
        ]);

        $this->recordFinancialEvent(
            FinancialEventType::Refund,
            $booking,
            $refundAmount,
            [
                'fedapay_ref' => $refundId,
                'status' => $refundStatus,
                'metadata' => [
                    'retained_amount' => $retainedAmount,
                    'idempotency_key' => $idempotencyKey,
                ],
            ],
        );
    }
}
