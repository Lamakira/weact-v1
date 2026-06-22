<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/** Email Producteur (vouvoiement, D-7.1.e) : la Face a refusé son booking (ugc-7-5). */
final class BookingRefusedMail extends BaseMail
{
    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return 'Votre booking a été refusé';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-refused',
            with: [
                'faceName' => $this->resolveFaceName(),
                'reasonLabel' => $this->resolveReason(),
                'refusedAt' => $this->booking->updated_at?->format('d/m/Y H:i') ?? 'Inconnue',
                'bookingUrl' => $this->buildBookingUrl(),
            ],
        );
    }

    private function resolveFaceName(): string
    {
        return (string) data_get($this->booking, 'face.userable.display_name', 'La Face');
    }

    private function resolveReason(): string
    {
        // D-7.5.f : cancellation_reason est du TEXTE LIBRE (RefuseBookingRequest
        // nullable|string|max:1000), PAS un enum → rendu brut + fallback.
        $reason = trim((string) ($this->booking->cancellation_reason ?? ''));

        return $reason !== '' ? $reason : 'Non spécifiée';
    }

    private function buildBookingUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/producer/bookings/{$this->booking->uuid}";
    }
}
