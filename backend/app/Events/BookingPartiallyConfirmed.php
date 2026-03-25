<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingPartiallyConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly User $confirmer,
    ) {}
}
