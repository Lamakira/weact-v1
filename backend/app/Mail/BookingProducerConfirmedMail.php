<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Face (tutoiement) : le Producteur a confirmé la fin de la prestation en premier.
 * `$dueAt` = échéance de paiement automatique sans action de sa part (d/m/Y),
 * null si elle est déjà passée (le paiement suit au prochain passage du traitement horaire).
 */
final class BookingProducerConfirmedMail extends BaseMail
{
    use DescribesBooking;

    public function __construct(
        public readonly Booking $booking,
        public readonly ?string $dueAt,
    ) {}

    protected function subjectLine(): string
    {
        return $this->producerName().' a confirmé la prestation : confirme à ton tour';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-producer-confirmed',
            with: [
                'producerName' => $this->producerName(),
                'dueAt' => $this->dueAt,
                'bookingUrl' => $this->bookingUrl(false),
            ],
        );
    }
}
