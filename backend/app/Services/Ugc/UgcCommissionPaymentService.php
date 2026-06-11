<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\BookingCommissionPaid;
use App\Models\Booking;
use App\Models\Mission;
use App\Services\FedapayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated settlement service for UGC commission payments (D-1.5.b).
 *
 * WeAct only charges its commission (`commission_ugc`) — never the product value
 * or the out-of-WeAct remuneration (D-1.5.a). There is NO escrow on the UGC path:
 *  - a paid UGC booking transitions pending → BookingStatus::CommissionPaid;
 *  - a paid UGC mission transitions pending_payment → MissionStatus::Published.
 *
 * Booking settlements record FinancialEvent audit rows (via the booking-scoped
 * RecordsFinancialEvent trait). Mission settlements DO NOT (D-1.5.g): the mission
 * audit is `commission_paid_at` + `fedapay_transaction_id`, idempotent on
 * `status === Published`.
 */
class UgcCommissionPaymentService
{
    use RecordsFinancialEvent;

    /**
     * FedaPay statuses that mean an in-flight transaction can no longer be reused
     * (the producer must get a brand-new checkout).
     *
     * @var list<string>
     */
    private const TERMINAL_FAILED_STATUSES = ['declined', 'canceled', 'refunded'];

    public function __construct(
        private readonly FedapayService $fedapayService,
    ) {}

    // -------------------------------------------------------------------------
    // Booking commission (pending → commission_paid)
    // -------------------------------------------------------------------------

    /**
     * Initiate a FedaPay checkout for a UGC booking commission.
     * Idempotent: a second call while a transaction is in flight regenerates the
     * checkout URL of the existing transaction instead of creating a new one.
     *
     * @return array{booking: Booking, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiateForBooking(Booking $booking): array
    {
        $lock = Cache::lock("ugc_commission_booking_{$booking->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour cette commission.'],
            ]);
        }

        try {
            /** @var Booking $booking */
            $booking = Booking::query()->findOrFail($booking->id);

            $this->assertBookingPayable($booking);

            if ($booking->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction((int) $booking->fedapay_transaction_id);

                if (! in_array($existing->status, self::TERMINAL_FAILED_STATUSES, true)) {
                    $regenerated = $this->fedapayService->regenerateTokenFromTransaction($existing);

                    return [
                        'booking' => $booking->fresh(),
                        'checkout_url' => $regenerated['checkout_url'],
                    ];
                }

                // Terminal failure — clear so a fresh checkout can be created.
                $booking->update(['fedapay_transaction_id' => null, 'payment_initiation_key' => null]);
                $booking = $booking->fresh();
            }

            $idempotencyKey = $booking->payment_initiation_key ?: Str::uuid()->toString();
            $booking->update(['payment_initiation_key' => $idempotencyKey]);

            $result = $this->fedapayService->initiatePaymentForUgcBooking($booking->fresh(), $idempotencyKey);

            return DB::transaction(function () use ($booking, $result, $idempotencyKey): array {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($locked->fedapay_transaction_id !== null) {
                    return ['booking' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
                }

                $locked->update([
                    'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                    'payment_initiation_key' => null,
                ]);

                $this->recordFinancialEvent(
                    FinancialEventType::PaymentInitiated,
                    $locked->fresh(),
                    (int) $locked->commission_ugc, // D-1.5.a : commission UNIQUEMENT
                    ['idempotency_key' => $idempotencyKey, 'metadata' => ['kind' => 'ugc_commission']],
                );

                return ['booking' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Settle an approved UGC booking commission: pending → commission_paid.
     * Idempotent and escrow-free. A payment landing on a non-pending (terminal)
     * booking is logged for ops and no-oped — never thrown — so the webhook job
     * is never poisoned into a retry storm.
     */
    public function markBookingCommissionPaid(Booking $booking, string $fedapayRef): Booking
    {
        return DB::transaction(function () use ($booking, $fedapayRef) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status === BookingStatus::CommissionPaid) {
                return $booking; // idempotent
            }

            if ($booking->status !== BookingStatus::Pending) {
                // Statut terminal (annulé/expiré/refusé) mais un paiement est tout de
                // même arrivé. NE PAS throw : l'exception s'échapperait de
                // HandleFedapayWebhook::handle() avant markProcessed() et le job
                // (ShouldQueue) partirait en retry storm vers failed_jobs. On
                // journalise pour traitement ops — le remboursement réel = story 2.5.
                Log::critical('UGC: paiement reçu sur un booking non-payable — remboursement ops requis', [
                    'booking_id' => $booking->id,
                    'status' => $booking->status->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $booking;
            }

            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentConfirmed)) {
                return $booking; // guard double event
            }

            $booking->update(['status' => BookingStatus::CommissionPaid, 'commission_paid_at' => now()]);
            $fresh = $booking->fresh();

            $this->recordFinancialEvent(
                FinancialEventType::PaymentConfirmed,
                $fresh,
                (int) $fresh->commission_ugc, // D-1.5.a : commission UNIQUEMENT
                ['fedapay_ref' => $fedapayRef, 'status' => 'confirmed', 'metadata' => ['kind' => 'ugc_commission']],
            );

            BookingCommissionPaid::dispatch($fresh); // PAS d'escrowService->lock()

            return $fresh;
        });
    }

    /**
     * Record a failed/cancelled UGC booking commission attempt.
     * Status is unchanged (stays pending) so the producer can retry. Idempotent
     * per (booking, PaymentFailed, fedapayRef).
     */
    public function markBookingCommissionFailed(Booking $booking, string $fedapayRef, string $reason): void
    {
        DB::transaction(function () use ($booking, $fedapayRef, $reason): void {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status === BookingStatus::CommissionPaid) {
                return; // déjà réglé : ne pas enregistrer un PaymentFailed contradictoire
            }

            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentFailed, $fedapayRef)) {
                return;
            }

            $this->recordFinancialEvent(
                FinancialEventType::PaymentFailed,
                $booking,
                (int) $booking->commission_ugc,
                ['fedapay_ref' => $fedapayRef, 'status' => 'failed', 'metadata' => ['kind' => 'ugc_commission', 'reason' => $reason]],
            );
        });
    }

    /**
     * Fallback reconciliation: poll FedaPay and settle if approved (webhook lag).
     */
    public function checkAndProcessBooking(Booking $booking): Booking
    {
        if ($booking->status !== BookingStatus::Pending || ! $booking->fedapay_transaction_id) {
            return $booking;
        }

        $transaction = $this->fedapayService->retrieveTransaction((int) $booking->fedapay_transaction_id);

        if ($transaction->status === 'approved') {
            return $this->markBookingCommissionPaid($booking, (string) ($transaction->reference ?? 'fedapay_poll'));
        }

        return $booking;
    }

    // -------------------------------------------------------------------------
    // Mission commission (pending_payment → published) — NO FinancialEvent (D-1.5.g)
    // -------------------------------------------------------------------------

    /**
     * Initiate a FedaPay checkout for a UGC mission publication commission.
     * Idempotent (same reuse/regenerate/reset semantics as the booking path) but
     * WITHOUT any FinancialEvent (the audit trait is booking-scoped — D-1.5.g).
     *
     * @return array{mission: Mission, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiateForMission(Mission $mission): array
    {
        $lock = Cache::lock("ugc_commission_mission_{$mission->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour cette commission.'],
            ]);
        }

        try {
            /** @var Mission $mission */
            $mission = Mission::query()->findOrFail($mission->id);

            $this->assertMissionPayable($mission);

            if ($mission->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction((int) $mission->fedapay_transaction_id);

                if (! in_array($existing->status, self::TERMINAL_FAILED_STATUSES, true)) {
                    $regenerated = $this->fedapayService->regenerateTokenFromTransaction($existing);

                    return [
                        'mission' => $mission->fresh(),
                        'checkout_url' => $regenerated['checkout_url'],
                    ];
                }

                $mission->update(['fedapay_transaction_id' => null, 'payment_initiation_key' => null]);
                $mission = $mission->fresh();
            }

            $idempotencyKey = $mission->payment_initiation_key ?: Str::uuid()->toString();
            $mission->update(['payment_initiation_key' => $idempotencyKey]);

            $result = $this->fedapayService->initiatePaymentForUgcMission($mission->fresh(), $idempotencyKey);

            return DB::transaction(function () use ($mission, $result): array {
                /** @var Mission $locked */
                $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);

                if ($locked->fedapay_transaction_id !== null) {
                    return ['mission' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
                }

                $locked->update([
                    'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                    'payment_initiation_key' => null,
                ]);

                return ['mission' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Settle an approved UGC mission commission: pending_payment → published.
     * Idempotent on `status === Published`. No FinancialEvent (D-1.5.g): audit is
     * `commission_paid_at` + `fedapay_transaction_id`. A payment landing on a
     * non-UGC or non-pending_payment mission is logged for ops and no-oped —
     * never thrown — so the webhook job is never poisoned into a retry storm.
     */
    public function markMissionCommissionPaid(Mission $mission, string $fedapayRef): Mission
    {
        return DB::transaction(function () use ($mission, $fedapayRef) {
            /** @var Mission $mission */
            $mission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($mission->type_mission !== MissionType::Ugc) {
                // Defense-in-depth : ce chemin ne doit JAMAIS publier une mission
                // standard (escrow). Disjonction déjà garantie par le filtre type du
                // lookup webhook — garde paranoïaque, no-op (jamais throw).
                Log::critical('UGC: settlement publication appelé sur une mission non-UGC — ignoré', [
                    'mission_id' => $mission->id,
                    'type_mission' => $mission->type_mission->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $mission;
            }

            if ($mission->status === MissionStatus::Published) {
                return $mission; // idempotent
            }

            if ($mission->status !== MissionStatus::PendingPayment) {
                // Statut terminal : paiement arrivé sur une mission non-publiable.
                // NE PAS throw (poison webhook) — journaliser pour l'ops (refund = 2.5).
                Log::critical('UGC: paiement reçu sur une mission non-publiable — remboursement ops requis', [
                    'mission_id' => $mission->id,
                    'status' => $mission->status->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $mission;
            }

            $mission->update([
                'status' => MissionStatus::Published,
                'commission_paid_at' => now(),
            ]);

            Log::info('UGC mission commission paid — mission published', [
                'mission_id' => $mission->id,
                'fedapay_ref' => $fedapayRef,
            ]);

            return $mission->fresh();
        });
    }

    /**
     * Record a failed/cancelled UGC mission commission attempt (log only).
     * Status is unchanged (stays pending_payment) so the producer can retry.
     */
    public function markMissionCommissionFailed(Mission $mission, string $fedapayRef, string $reason): void
    {
        Log::info('UGC mission commission payment failed — mission stays pending_payment', [
            'mission_id' => $mission->id,
            'fedapay_ref' => $fedapayRef,
            'reason' => $reason,
            'status' => $mission->status->value,
        ]);
    }

    /**
     * Fallback reconciliation: poll FedaPay and publish if approved (webhook lag).
     */
    public function checkAndProcessMission(Mission $mission): Mission
    {
        if ($mission->status !== MissionStatus::PendingPayment || ! $mission->fedapay_transaction_id) {
            return $mission;
        }

        $transaction = $this->fedapayService->retrieveTransaction((int) $mission->fedapay_transaction_id);

        if ($transaction->status === 'approved') {
            return $this->markMissionCommissionPaid($mission, (string) ($transaction->reference ?? 'fedapay_poll'));
        }

        return $mission;
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    private function assertBookingPayable(Booking $booking): void
    {
        if ($booking->type_contenu !== 'UGC') {
            throw ValidationException::withMessages([
                'status' => ['Ce booking n\'est pas une dotation UGC.'],
            ]);
        }

        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['La commission de ce booking ne peut pas être payée dans son état actuel.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertMissionPayable(Mission $mission): void
    {
        if ($mission->type_mission !== MissionType::Ugc) {
            throw ValidationException::withMessages([
                'status' => ['Cette mission n\'est pas une dotation UGC.'],
            ]);
        }

        if ($mission->status !== MissionStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'status' => ['La commission de cette mission ne peut pas être payée dans son état actuel.'],
            ]);
        }
    }
}
