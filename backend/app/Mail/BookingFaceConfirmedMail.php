<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : la Face a confirmé la prestation en premier — échéance pour signaler une absence. */
final class BookingFaceConfirmedMail extends BaseMail
{
    /**
     * @param  string  $dueAt  Échéance du paiement automatique, déjà formatée en fuseau métier (d/m/Y H:i)
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly string $dueAt,
    ) {}

    protected function subjectLine(): string
    {
        return 'La Face a confirmé la prestation : signalez une absence avant le '.$this->dueAt;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-face-confirmed',
            with: [
                'faceName' => (string) data_get($this->booking, 'face.userable.display_name', 'La Face'),
                'dueAt' => $this->dueAt,
                'bookingUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/bookings/{$this->booking->uuid}",
            ],
        );
    }
}
