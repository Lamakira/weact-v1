<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\BookingCancellationReason;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $cancelledBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Votre booking #{$this->booking->id} a été annulé",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-cancelled',
            with: [
                'booking' => $this->booking,
                'cancellerName' => $this->cancellerName(),
                'reasonLabel' => $this->reasonLabel(),
                'cancelledByFace' => $this->cancelledBy === 'face',
                'cancelledAt' => $this->cancelledAt(),
            ],
        );
    }

    private function cancellerName(): string
    {
        if ($this->cancelledBy === 'face') {
            return (string) ($this->booking->face?->userable?->display_name ?? 'La Face');
        }

        return (string) ($this->booking->producer?->userable?->display_name ?? 'Le Producteur');
    }

    private function reasonLabel(): string
    {
        $reason = $this->booking->cancellation_reason;

        if (! $reason) {
            return 'Non spécifiée';
        }

        $enum = BookingCancellationReason::tryFrom($reason);

        return $enum?->label() ?? $reason;
    }

    private function cancelledAt(): string
    {
        return $this->booking->updated_at?->format('d/m/Y H:i') ?? 'Inconnue';
    }
}
