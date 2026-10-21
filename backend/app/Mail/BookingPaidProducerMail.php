<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement) : son paiement est bien reçu. Booking cash (BookingPaid) ou
 * règlement d'une demande UGC (BookingCommissionPaid, $ugc) : la prochaine étape diffère.
 */
final class BookingPaidProducerMail extends BaseMail
{
    use DescribesBooking;

    public function __construct(
        public readonly Booking $booking,
        public readonly bool $ugc = false,
    ) {}

    protected function subjectLine(): string
    {
        return $this->ugc
            ? 'Votre règlement est bien reçu'
            : 'Votre paiement est bien reçu : booking confirmé avec '.$this->faceName();
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-paid-producer',
            with: [
                'faceName' => $this->faceName(),
                'ugc' => $this->ugc,
                'bookingDate' => $this->ugc ? null : $this->bookingDateLabel(),
                'bookingLocation' => $this->ugc ? null : $this->booking->lieu,
                'formattedAmount' => $this->formatMoney((int) $this->booking->montant_total_producteur),
                'bookingUrl' => $this->bookingUrl(true),
            ],
        );
    }
}
