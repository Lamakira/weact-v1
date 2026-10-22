<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement) : booking payé sans confirmation ni signalement — rappel de confirmer la fin de la prestation. */
final class BookingCompletionReminderFaceMail extends BaseMail
{
    use DescribesBooking;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return 'Confirmez la fin de votre prestation';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-completion-reminder-face',
            with: [
                'producerName' => $this->producerName(),
                'bookingUrl' => $this->bookingUrl(false),
            ],
        );
    }
}
