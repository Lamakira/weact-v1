<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement) : le Producteur a signalé son absence — délai pour contester. */
final class BookingNoShowFaceMail extends BaseMail
{
    /**
     * @param  string  $dueAt  Fin de la fenêtre de contestation, déjà formatée en fuseau métier (d/m/Y H:i)
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly string $dueAt,
    ) {}

    protected function subjectLine(): string
    {
        return 'Une absence a été signalée : tu peux contester jusqu\'au '.$this->dueAt;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-no-show-face',
            with: [
                'producerName' => (string) data_get($this->booking, 'producer.userable.display_name', 'Le Producteur'),
                'dueAt' => $this->dueAt,
                'bookingUrl' => rtrim((string) config('app.frontend_url'), '/')."/face/bookings/{$this->booking->uuid}",
            ],
        );
    }
}
