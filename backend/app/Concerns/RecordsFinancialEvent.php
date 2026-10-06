<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\FinancialEvent;
use App\Models\MissionPaymentCandidature;
use Illuminate\Support\Str;

/**
 * Provides reusable methods for recording immutable FinancialEvent audit entries.
 *
 * Usage: `use RecordsFinancialEvent;` in any service that performs financial operations
 * (BookingService, EscrowService, WalletService, etc.).
 *
 * All methods are designed to be called INSIDE an existing DB::transaction() context.
 */
trait RecordsFinancialEvent
{
    /**
     * Record a FinancialEvent inside the calling DB::transaction context.
     *
     * @param  array<string, mixed>  $extra  Optional overrides: fedapay_ref, idempotency_key, status, metadata
     */
    protected function recordFinancialEvent(
        FinancialEventType $type,
        Booking $booking,
        int $amount,
        array $extra = [],
    ): FinancialEvent {
        return FinancialEvent::create([
            'type' => $type,
            'booking_id' => $booking->id,
            'amount' => $amount,
            'fedapay_ref' => $extra['fedapay_ref'] ?? null,
            'idempotency_key' => $extra['idempotency_key'] ?? Str::uuid()->toString(),
            'status' => $extra['status'] ?? 'pending',
            'metadata' => $extra['metadata'] ?? null,
        ]);
    }

    /**
     * Record a FinancialEvent for a mission attendance settlement (release/refund).
     * Uses booking_id = null and a deterministic idempotency_key per (entry, type).
     *
     * MUST be called inside an existing DB::transaction() context.
     *
     * @param  array<string, mixed>  $extra  Optional overrides: idempotency_key, status, metadata
     */
    protected function recordMissionAttendanceFinancialEvent(
        FinancialEventType $type,
        MissionPaymentCandidature $entry,
        int $amount,
        array $extra = [],
    ): FinancialEvent {
        $defaultMetadata = [
            'mission_payment_candidature_id' => $entry->id,
            'mission_payment_id' => $entry->mission_payment_id,
            'face_id' => $entry->face_id,
        ];

        return FinancialEvent::create([
            'type' => $type,
            'booking_id' => null,
            'amount' => $amount,
            'fedapay_ref' => null,
            'idempotency_key' => $extra['idempotency_key'] ?? "mission_attendance_{$type->value}:{$entry->id}",
            'status' => $extra['status'] ?? 'completed',
            'metadata' => array_merge($defaultMetadata, $extra['metadata'] ?? []),
        ]);
    }

    /**
     * Audit a FedaPay transaction we DETACHED from its entity (released cash selection,
     * deleted hybrid escrow entry) while the transaction may still be paid later: a late
     * `transaction.approved` webhook then finds no row, and ops needs this record to
     * reconcile manually (HandleFedapayWebhook escalates CRITICAL with it).
     *
     * `fedapay_ref` carries the detached transaction id; `status` the FedaPay status when
     * known (else 'detached'). Idempotent per transaction id. MUST be called inside an
     * existing DB::transaction() context.
     *
     * @param  array<string, mixed>  $context  Entity ids and free-form audit data
     */
    protected function recordDetachedPayment(
        string $entityType,
        int $entityId,
        string $fedapayTransactionId,
        ?string $fedapayStatus,
        int $amount,
        array $context = [],
    ): FinancialEvent {
        return FinancialEvent::firstOrCreate(
            ['idempotency_key' => "payment_detached:{$fedapayTransactionId}"],
            [
                'type' => FinancialEventType::PaymentDetached,
                'booking_id' => null,
                'amount' => $amount,
                'fedapay_ref' => $fedapayTransactionId,
                'status' => $fedapayStatus ?? 'detached',
                'metadata' => array_merge($context, [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'fedapay_status' => $fedapayStatus,
                    'detached_at' => now()->toIso8601String(),
                ]),
            ],
        );
    }

    /**
     * Check if a FinancialEvent already exists for the given booking + type + optional fedapay_ref.
     */
    protected function hasExistingFinancialEvent(
        int $bookingId,
        FinancialEventType $type,
        ?string $fedapayRef = null,
    ): bool {
        $query = FinancialEvent::forBooking($bookingId)->ofType($type);

        if ($fedapayRef !== null) {
            $query->where('fedapay_ref', $fedapayRef);
        }

        return $query->exists();
    }
}
