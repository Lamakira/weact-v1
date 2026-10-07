<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : booking payé sans confirmation ni signalement — paiement auto de la Face prévu. */
final class BookingCompletionReminderMail extends BaseMail
{
    public function __construct(
        public readonly Booking $booking,
        public readonly string $autoCompletionDate,
    ) {}

    protected function subjectLine(): string
    {
        return 'Confirmez votre prestation ou signalez une absence';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-completion-reminder',
            with: [
                'faceName' => (string) data_get($this->booking, 'face.userable.display_name', 'La Face'),
                'autoCompletionDate' => $this->autoCompletionDate,
                'bookingUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/bookings/{$this->booking->uuid}",
            ],
        );
    }
}
