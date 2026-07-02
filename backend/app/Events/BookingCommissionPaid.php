<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a UGC booking's WeAct commission is confirmed paid
 * (status pending → commission_paid). Distinct from BookingPaid, whose cash
 * listeners assume the escrow / double-confirm flow (not applicable to UGC).
 * No listener is wired in epic 1 — notifications land in epics 2+.
 */
class BookingCommissionPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}
}
