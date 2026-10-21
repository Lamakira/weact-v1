<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\DisputeResolutionOutcome;
use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/**
 * Email d'issue d'une contestation de booking (absence Face / annulation Producteur tardive).
 * Envoyé à la partie qui n'a PAS d'e-mail de crédit wallet sur l'issue (perdante, ou gagnante sans
 * crédit). Producteur en vouvoiement, Face en tutoiement.
 */
final class BookingDisputeSettledMail extends BaseMail
{
    use DescribesBooking;

    /**
     * @param  bool  $contested  la Face a contesté (décision d'un administrateur) ; sinon règlement automatique
     * @param  bool  $penalty  une pénalité est appliquée au profil de la Face (absence confirmée)
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly bool $forProducer,
        public readonly DisputeResolutionOutcome $outcome,
        public readonly bool $contested,
        public readonly bool $penalty,
    ) {}

    protected function subjectLine(): string
    {
        $inFavorOfRecipient = $this->forProducer
            ? $this->outcome === DisputeResolutionOutcome::FavorProducer
            : $this->outcome === DisputeResolutionOutcome::FavorFace;

        if ($inFavorOfRecipient) {
            return $this->forProducer
                ? 'Litige réglé : décision en votre faveur'
                : 'Litige réglé : décision en votre faveur';
        }

        return $this->outcome === DisputeResolutionOutcome::FavorProducer
            ? 'Litige réglé : décision en faveur du Producteur'
            : 'Litige réglé : décision en faveur de la Face';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-dispute-settled',
            with: [
                'forProducer' => $this->forProducer,
                'favorProducer' => $this->outcome === DisputeResolutionOutcome::FavorProducer,
                'contested' => $this->contested,
                'penalty' => $this->penalty,
                'faceName' => $this->faceName(),
                'producerName' => $this->producerName(),
                'bookingUrl' => $this->bookingUrl($this->forProducer),
            ],
        );
    }
}
