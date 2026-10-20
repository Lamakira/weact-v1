<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

/**
 * Libellés communs aux e-mails de booking (propriété `$booking` du Mailable requise).
 * Les dates sont rendues dans le fuseau métier (la base stocke en UTC).
 */
trait DescribesBooking
{
    protected function faceName(): string
    {
        $name = trim((string) data_get($this->booking, 'face.userable.display_name', ''));

        return $name !== '' ? $name : 'La Face';
    }

    protected function producerName(): string
    {
        $name = trim((string) data_get($this->booking, 'producer.userable.display_name', ''));

        return $name !== '' ? $name : 'Le Producteur';
    }

    protected function bookingDateLabel(): ?string
    {
        return $this->booking->date_debut
            ?->copy()
            ->setTimezone((string) config('app.business_timezone'))
            ->locale('fr')
            ->translatedFormat('l d F Y');
    }

    /** Heure de début, seulement si le booking en porte une (minuit = pas d'horaire saisi). */
    protected function startTimeLabel(): ?string
    {
        $start = $this->booking->date_debut?->copy()->setTimezone((string) config('app.business_timezone'));

        if ($start === null || $start->format('H:i') === '00:00') {
            return null;
        }

        return $start->format('H\hi');
    }

    protected function formatMoney(int|float $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ').' XOF';
    }

    protected function bookingUrl(bool $forProducer): string
    {
        $role = $forProducer ? 'producer' : 'face';

        return rtrim((string) config('app.frontend_url'), '/')."/{$role}/bookings/{$this->booking->uuid}";
    }
}
