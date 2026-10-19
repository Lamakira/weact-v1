<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement) : relance de paiement d'un booking accepté, échéance accepted_at + 24 h (lot B11). */
final class BookingPaymentReminderMail extends BaseMail
{
    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return 'Payez votre booking pour le confirmer';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-payment-reminder',
            with: [
                'faceName' => (string) data_get($this->booking, 'face.userable.display_name', 'La Face'),
                'bookingDate' => $this->booking->date_debut !== null
                    ? Booking::formatForBusiness($this->booking->date_debut, 'd/m/Y')
                    : null,
                'formattedAmount' => number_format((int) $this->booking->montant_total_producteur, 0, ',', ' ').' XOF',
                'deadlineLabel' => (string) $this->booking->paymentDeadlineLabel(),
                'bookingUrl' => rtrim((string) config('app.frontend_url'), '/')."/producer/bookings/{$this->booking->uuid}",
            ],
        );
    }
}
