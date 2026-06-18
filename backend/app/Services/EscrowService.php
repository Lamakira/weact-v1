<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\WalletCreditMotif;
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
            description: 'Booking : escrow libéré',
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
     * Unwind a UGC hybrid escrow on a pre-acceptance settlement refund (RH.2).
     * Tolerant variant of markRefundedForNoShow: no escrow → no-op (product-only
     * booking has nothing sequestered), already released/refunded → no-op (idempotent).
     * Does NOT credit a wallet nor record a FinancialEvent — the full producer credit
     * and the Refund audit row are handled by UgcRefundService (D-RH2.g).
     * MUST be called inside an existing DB::transaction().
     */
    public function markRefundedForUgc(Booking $booking): void
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = $booking->escrowTransaction()->lockForUpdate()->first();

        if ($escrow === null) {
            return; // produit-seul : rien de séquestré
        }

        if (in_array($escrow->status, [
            EscrowStatus::Released->value,
            EscrowStatus::Refunded->value,
        ], true)) {
            return; // idempotent
        }

        $escrow->update([
            'status' => EscrowStatus::Refunded->value,
            'refunded_at' => now(),
        ]);
    }

    /**
     * D-5.0.a/c — suspension anti-arnaque : rembourser au Producteur l'escrow « net Face »
     * séquestré (montant_face_recoit UNIQUEMENT ; WeAct garde sa commission de service, D-5.0.c).
     * Idempotent : pas d'escrow (produit-seul) → false ; déjà released/refunded/pending → false.
     * MUST be called inside an existing DB::transaction().
     *
     * Path DÉDIÉ, distinct de markRefundedForUgc (flip sans crédit, RH.2) et de
     * UgcRefundService::settleLockedBooking (crédite montant_total_producteur, refund
     * pré-acceptation). Mouvement atomique « flip + crédit montant_face_recoit + audit
     * Refund » + retour bool pour que l'appelant ne dispatch/notifie qu'au vrai remboursement.
     *
     * @return bool true si remboursé MAINTENANT (crédit + audit émis), false sinon (no-op).
     */
    public function refundUgcSuspensionToProducer(Booking $booking, WalletService $walletService): bool
    {
        /** @var EscrowTransaction|null $escrow */
        $escrow = $booking->escrowTransaction()->lockForUpdate()->first();

        if ($escrow === null) {
            return false; // produit-seul : rien de séquestré
        }

        if (in_array($escrow->status, [
            EscrowStatus::Released->value,
            EscrowStatus::Refunded->value,
            EscrowStatus::Pending->value,
        ], true)) {
            return false; // idempotent
        }

        $escrow->update([
            'status' => EscrowStatus::Refunded->value,
            'refunded_at' => now(),
        ]);

        $walletService->credit(
            userId: $booking->producer_id,
            amount: $booking->montant_face_recoit,
            booking: $booking,
            description: WalletCreditMotif::UgcSuspensionRefund->label(),
        );

        $this->recordFinancialEvent(
            FinancialEventType::Refund,
            $booking,
            $booking->montant_face_recoit,
            ['status' => 'completed', 'metadata' => ['refund_channel' => 'wallet', 'kind' => 'ugc_suspension']],
        );

        return true;
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
            description: 'Booking : remboursement annulation (90%)',
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
