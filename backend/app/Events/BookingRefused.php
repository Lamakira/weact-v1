<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRefused
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $automatic  Refus automatique (lot B9) : la Face a accepté une autre
     *                           demande sur des dates qui chevauchent celle-ci.
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly bool $automatic = false,
    ) {}
}
