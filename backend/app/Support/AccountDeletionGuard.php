<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;

/**
 * Source unique des raisons pour lesquelles un compte (User + Face/Producer)
 * ne peut pas être supprimé : argent détenu, dû ou en vol. Utilisée par la
 * suppression self-service (UserDataController) ET par les suppressions admin
 * (Face / Producer), pour que les deux chemins refusent exactement pareil.
 *
 * Lecture pure : aucun mouvement d'argent. Les séquestres `Locked` passent par
 * LockedEscrowGuard (même règle que la garde admin historique).
 */
final class AccountDeletionGuard
{
    /**
     * Phrases françaises, une par raison de blocage ; vide = suppression possible.
     *
     * @return list<string>
     */
    public static function blockers(User $user): array
    {
        $blockers = [];

        $balance = (int) $user->balance;
        if ($balance > 0) {
            $blockers[] = 'Votre portefeuille contient encore '.number_format($balance, 0, ',', ' ').' FCFA : retirez-les avant de supprimer votre compte.';
        }

        $hasPendingWithdrawal = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        // Auto FedaPay withdrawal: the debit stays `pending` until the payout settles;
        // a payout.failed would re-credit the (anonymized) wallet.
        $hasPendingWalletDebit = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'debit')
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingWithdrawal || $hasPendingWalletDebit) {
            $blockers[] = 'Une demande de retrait est en cours de traitement : attendez son traitement avant de supprimer votre compte.';
        }

        if (self::hasMoneyInPlay($user)) {
            $blockers[] = 'Un booking ou une mission est en cours avec de l\'argent en jeu : terminez-le ou contactez le support.';
        }

        if (self::hasPaidUgcCommissionInFlight($user)) {
            $blockers[] = 'Une commission UGC est en cours sur une mission ou un booking : attendez sa clôture ou contactez le support.';
        }

        return $blockers;
    }

    /**
     * Escrow locked, absence/late-cancellation awaiting settlement, open dispute,
     * or a payment that FedaPay may still settle (checkout started, not paid yet).
     */
    private static function hasMoneyInPlay(User $user): bool
    {
        $userable = $user->userable;

        if ($userable instanceof Face && LockedEscrowGuard::forFace($userable)) {
            return true;
        }
        if ($userable instanceof Producer && LockedEscrowGuard::forProducer($userable)) {
            return true;
        }

        // Bookings.face_id / producer_id are users.id.
        $hasUnsettledBooking = Booking::query()
            ->where(fn ($q) => $q->where('face_id', $user->id)->orWhere('producer_id', $user->id))
            ->where(function ($q): void {
                $q->whereHas('escrowTransaction', fn ($e) => $e->where('status', EscrowStatus::Locked->value))
                    ->orWhere(fn ($b) => $b->pendingSettlement())
                    ->orWhere(fn ($b) => $b->whereNotNull('disputed_at')->whereNull('dispute_resolved_at'))
                    // FedaPay checkout started while the booking still awaits payment.
                    ->orWhere(fn ($b) => $b->whereNotNull('fedapay_transaction_id')
                        ->where('status', BookingStatus::Accepted->value));
            })
            ->exists();

        if ($hasUnsettledBooking) {
            return true;
        }

        // Mission / hybrid UGC candidature escrow (face_id = faces.id, producer_id = producers.id):
        // locked, or pending with a FedaPay transaction already opened.
        $entries = MissionPaymentCandidature::query()->where(function ($q): void {
            $q->where('escrow_status', EscrowStatus::Locked->value)
                ->orWhere(fn ($p) => $p->where('escrow_status', EscrowStatus::Pending->value)
                    ->whereNotNull('fedapay_transaction_id'));
        });

        if ($user->userable_type === Face::class && $user->userable_id !== null) {
            // Lot B4 : une place libérée (Face expirée) garde l'argent du Producer, pas celui de la Face.
            return (clone $entries)->where('face_id', $user->userable_id)->whereNull('freed_at')->exists();
        }

        if ($user->userable_type === Producer::class && $user->userable_id !== null) {
            if ((clone $entries)->whereHas('missionPayment', fn ($q) => $q->where('producer_id', $user->userable_id))->exists()) {
                return true;
            }

            // Mission payment pending with a FedaPay transaction opened.
            return MissionPayment::query()
                ->where('producer_id', $user->userable_id)
                ->where('status', MissionPaymentStatus::Pending->value)
                ->whereNotNull('fedapay_transaction_id')
                ->exists();
        }

        return false;
    }

    /**
     * Producer whose UGC commission was paid and not refunded yet, on a mission or
     * booking that is not terminal: a later expiry / refund would credit the
     * anonymized wallet.
     */
    private static function hasPaidUgcCommissionInFlight(User $user): bool
    {
        if ($user->userable_type !== Producer::class || $user->userable_id === null) {
            return false;
        }

        $missionInFlight = Mission::query()
            ->where('producer_id', $user->userable_id)
            ->whereNotNull('commission_paid_at')
            ->whereNull('commission_refunded_at')
            ->whereNotIn('status', [MissionStatus::Closed->value, MissionStatus::Completed->value])
            ->exists();

        if ($missionInFlight) {
            return true;
        }

        return Booking::query()
            ->where('producer_id', $user->id)
            ->whereNotNull('commission_paid_at')
            ->whereNull('commission_refunded_at')
            ->whereNotIn('status', [
                BookingStatus::Completed->value,
                BookingStatus::Refused->value,
                BookingStatus::Expired->value,
                BookingStatus::CancelledByFace->value,
                BookingStatus::CancelledByProducer->value,
                BookingStatus::NoShow->value,
            ])
            ->exists();
    }
}
