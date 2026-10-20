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
        public readonly string $autoCompletionDate,
    ) {}

    protected function subjectLine(): string
    {
        return 'Confirme la fin de ta prestation';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-completion-reminder-face',
            with: [
                'producerName' => $this->producerName(),
                'autoCompletionDate' => $this->autoCompletionDate,
                'bookingUrl' => $this->bookingUrl(false),
            ],
        );
    }
}
