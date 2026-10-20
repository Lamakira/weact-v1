<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Face (tutoiement) : le Producteur a payé son booking cash, le montant est sécurisé et le chat est ouvert. */
final class BookingPaidFaceMail extends BaseMail
{
    use DescribesBooking;

    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return 'Paiement sécurisé : ton booking avec '.$this->producerName().' est confirmé';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-paid-face',
            with: [
                'producerName' => $this->producerName(),
                'bookingDate' => $this->bookingDateLabel(),
                'startTime' => $this->startTimeLabel(),
                'bookingLocation' => $this->booking->lieu,
                'formattedAmount' => $this->formatMoney((int) $this->booking->montant_face_recoit),
                'bookingUrl' => $this->bookingUrl(false),
            ],
        );
    }
}
