<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\DisputeResolutionOutcome;
use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fin de la fenêtre de contestation d'un booking (absence Face / annulation Producteur
 * tardive) : règlement automatique (72 h sans contestation) ou décision admin.
 * `$creditedAmount` = somme réellement créditée au wallet du bénéficiaire.
 */
class BookingDisputeSettled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly DisputeResolutionOutcome $outcome,
        public readonly int $creditedAmount,
    ) {}
}
