<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\BookingRating;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly BookingRating $rating,
    ) {}
}
