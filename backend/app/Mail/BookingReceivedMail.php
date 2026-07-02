<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use Illuminate\Mail\Mailables\Content;

final class BookingReceivedMail extends BaseMail
{
    public function __construct(
        public readonly Face $face,
        public readonly Producer $producer,
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        $producerName = $this->resolveProducerName();
        $date = $this->formatBookingDate();

        // A UGC dotation has no shoot date → omit the "pour le {date}" suffix.
        return $date !== null
            ? "Nouvelle demande de booking de {$producerName} pour le {$date}"
            : "Nouvelle demande de booking de {$producerName}";
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-received',
            with: [
                'faceFirstName' => $this->resolveFaceFirstName(),
                'producerName' => $this->resolveProducerName(),
                'bookingDate' => $this->formatBookingDate(),
                'bookingLocation' => $this->booking->lieu,
                'typeContenu' => $this->booking->type_contenu,
                'dureeHeures' => $this->booking->duree_heures,
                'formattedAmount' => $this->formatAmount(),
                'bookingUrl' => $this->buildBookingUrl(),
            ],
        );
    }

    private function resolveFaceFirstName(): string
    {
        $prenom = trim((string) $this->face->prenom);

        return $prenom !== '' ? $prenom : $this->face->display_name;
    }

    private function resolveProducerName(): string
    {
        $name = trim((string) $this->producer->display_name);

        return $name !== '' ? $name : 'Le Producteur';
    }

    private function formatBookingDate(): ?string
    {
        return $this->booking->date_debut?->locale('fr')->translatedFormat('l d F Y');
    }

    private function formatAmount(): string
    {
        return number_format($this->booking->montant_face_recoit, 0, ',', ' ').' XOF';
    }

    private function buildBookingUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/face/bookings/'.$this->booking->uuid;
    }
}
