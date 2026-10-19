<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Face (vouvoiement) : relance unique, il reste 24 h pour répondre à une demande de booking (lot B10). */
final class BookingResponseReminderMail extends BaseMail
{
    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une demande de booking attend votre réponse';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-response-reminder',
            with: [
                'producerName' => (string) data_get($this->booking, 'producer.userable.display_name', 'Le Producteur'),
                'typeContenu' => $this->booking->type_contenu,
                'bookingDate' => $this->booking->date_debut !== null
                    ? Booking::formatForBusiness($this->booking->date_debut, 'd/m/Y')
                    : null,
                'deadlineLabel' => (string) $this->booking->faceResponseDeadlineLabel(),
                'bookingUrl' => rtrim((string) config('app.frontend_url'), '/')."/face/bookings/{$this->booking->uuid}",
            ],
        );
    }
}
