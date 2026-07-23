<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Content;

/**
 * Email Producteur (vouvoiement, D-7.1.e) : la Face a accepté son booking.
 *
 * Pendant symétrique de BookingRefusedMail. Réservé aux bookings NON-UGC : la
 * variante UGC de l'acceptation est UgcDealAcceptedMail (« expédiez le produit »).
 * L'enjeu porté par cet email est le paiement sous 24h — passé ce délai,
 * ExpireUnpaidBookingsCommand fait expirer le booking accepté et non payé.
 */
final class BookingAcceptedMail extends BaseMail
{
    public function __construct(
        public readonly Booking $booking,
    ) {}

    protected function subjectLine(): string
    {
        return $this->resolveFaceName().' a accepté votre booking — paiement sous 24h';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-accepted',
            with: [
                'faceName' => $this->resolveFaceName(),
                'bookingDate' => $this->formatBookingDate(),
                'bookingLocation' => $this->booking->lieu,
                'dureeHeures' => $this->booking->duree_heures,
                'formattedAmount' => $this->formatAmount(),
                'paymentDeadline' => $this->formatPaymentDeadline(),
                'bookingUrl' => $this->buildBookingUrl(),
            ],
        );
    }

    private function resolveFaceName(): string
    {
        $name = trim((string) data_get($this->booking, 'face.userable.display_name', ''));

        return $name !== '' ? $name : 'La Face';
    }

    private function formatBookingDate(): ?string
    {
        return $this->booking->date_debut?->locale('fr')->translatedFormat('l d F Y');
    }

    /** Montant réclamé au Producteur (rémunération Face + frais de service). */
    private function formatAmount(): string
    {
        return number_format((float) $this->booking->montant_total_producteur, 0, ',', ' ').' XOF';
    }

    /**
     * Échéance = accepted_at + 24h (fenêtre d'ExpireUnpaidBookingsCommand).
     * BookingService::accept() pose toujours accepted_at avant le dispatch ; le
     * fallback ne couvre qu'un appel direct au mailable avec un booking non accepté.
     */
    private function formatPaymentDeadline(): ?string
    {
        return $this->booking->accepted_at
            ?->copy()
            ->addDay()
            ->locale('fr')
            ->translatedFormat('l d F Y à H\hi');
    }

    private function buildBookingUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')."/producer/bookings/{$this->booking->uuid}";
    }
}
