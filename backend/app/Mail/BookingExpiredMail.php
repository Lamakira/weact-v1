<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\DescribesBooking;
use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/**
 * Email aux deux parties : le booking a expiré. `$kind` = motif (KIND_*), `$forProducer` = destinataire.
 * Producteur en vouvoiement, Face en tutoiement.
 */
final class BookingExpiredMail extends BaseMail
{
    use DescribesBooking;

    public const KIND_FACE_NO_RESPONSE = 'face_no_response';

    public const KIND_PRODUCER_NO_PAYMENT = 'producer_no_payment';

    public const KIND_UGC_COMMISSION = 'ugc_commission';

    public function __construct(
        public readonly Booking $booking,
        public readonly string $kind,
        public readonly bool $forProducer,
    ) {}

    /** Motif d'expiration déduit de l'état du booking (même règle que la notification in-app). */
    public static function kindFor(Booking $booking): string
    {
        // Lot B10 : demande UGC dont la commission n'a jamais été réglée.
        if ($booking->type_contenu === 'UGC' && $booking->commission_paid_at === null) {
            return self::KIND_UGC_COMMISSION;
        }

        return $booking->accepted_at === null
            ? self::KIND_FACE_NO_RESPONSE
            : self::KIND_PRODUCER_NO_PAYMENT;
    }

    protected function subjectLine(): string
    {
        if ($this->forProducer) {
            return match ($this->kind) {
                self::KIND_FACE_NO_RESPONSE => 'Votre demande de booking a expiré : '.$this->faceName().' n\'a pas répondu',
                self::KIND_UGC_COMMISSION => 'Votre demande UGC a expiré faute de règlement',
                default => 'Votre booking a expiré faute de paiement',
            };
        }

        return match ($this->kind) {
            self::KIND_FACE_NO_RESPONSE => 'Une demande de booking a expiré faute de réponse',
            self::KIND_UGC_COMMISSION => 'Une demande UGC a expiré',
            default => 'Un booking accepté a expiré faute de paiement',
        };
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-expired',
            with: [
                'forProducer' => $this->forProducer,
                'kind' => $this->kind,
                'faceName' => $this->faceName(),
                'producerName' => $this->producerName(),
                'bookingDate' => $this->bookingDateLabel(),
                'bookingUrl' => $this->bookingUrl($this->forProducer),
            ],
        );
    }
}
