<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\FinancialEvent;
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
