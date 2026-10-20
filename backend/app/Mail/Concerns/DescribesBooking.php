<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

/**
 * Libellés communs aux e-mails de booking (propriété `$booking` du Mailable requise).
 * Les dates de tournage sont des dates calendaires sans heure : jamais d'heure de début affichée.
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

    /** `date_debut` est une date calendaire (00:00 UTC, sans heure) : aucune conversion de fuseau. */
    protected function bookingDateLabel(): ?string
    {
        return $this->booking->date_debut
            ?->copy()
            ->locale('fr')
            ->translatedFormat('l d F Y');
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
