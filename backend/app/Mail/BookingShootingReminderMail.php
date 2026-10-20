<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email aux deux parties la veille du tournage d'un booking cash payé. Producteur en vouvoiement, Face en tutoiement. */
final class BookingShootingReminderMail extends BaseMail
{
    use DescribesBooking;

    public function __construct(
        public readonly Booking $booking,
        public readonly bool $forProducer,
    ) {}

    protected function subjectLine(): string
    {
        return $this->forProducer
            ? 'Rappel : votre tournage avec '.$this->faceName().' a lieu demain'
            : 'Rappel : ton tournage avec '.$this->producerName().' a lieu demain';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-shooting-reminder',
            with: [
                'forProducer' => $this->forProducer,
                'otherName' => $this->forProducer ? $this->faceName() : $this->producerName(),
                'bookingDate' => $this->bookingDateLabel(),
                'dureeHeures' => $this->booking->duree_heures,
                'bookingLocation' => $this->booking->lieu,
                'bookingUrl' => $this->bookingUrl($this->forProducer),
            ],
        );
    }
}
