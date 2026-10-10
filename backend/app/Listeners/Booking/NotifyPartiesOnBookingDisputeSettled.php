<?php

declare(strict_types=1);

namespace App\Listeners\Booking;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Events\BookingDisputeSettled;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifications in-app aux deux parties au règlement d'un litige booking.
 * Chaque notification est isolée et non-fatale.
 */
#[AsEventListener(event: BookingDisputeSettled::class)]
class NotifyPartiesOnBookingDisputeSettled
{
    public function handle(BookingDisputeSettled $event): void
    {
        $booking = $event->booking;
        $amount = number_format($event->creditedAmount, 0, ',', ' ');
        // Seul le chemin favor_producer distingue absence / annulation (le statut NoShow est conservé).
        $isNoShow = $booking->status === BookingStatus::NoShow;

        $penalty = $isNoShow ? ' Une pénalité a été appliquée à votre profil.' : '';
        $contested = $booking->disputed_at !== null;

        if ($event->outcome === DisputeResolutionOutcome::FavorProducer) {
            // Deuxième personne pour le destinataire ; sans contestation, aucun administrateur n'est intervenu.
            $producerMessage = $contested
                ? "L'administrateur a tranché en votre faveur : {$amount} XOF ont été crédités dans votre portefeuille."
                : "Aucune contestation de la Face : vous avez été remboursé de {$amount} XOF sur votre wallet.";
            $faceMessage = $contested
                ? "L'administrateur a tranché en faveur du Producteur.{$penalty}"
                : "Délai de contestation écoulé : le Producteur a été remboursé.{$penalty}";
        } else {
            $producerMessage = "L'administrateur a tranché en faveur de la Face : le paiement lui est versé.";
            $faceMessage = "L'administrateur a tranché en votre faveur : {$amount} XOF ont été ajoutés à votre portefeuille.";
        }

        $this->notify($booking->producer_id, $producerMessage, "/producer/bookings/{$booking->uuid}", $booking->id);
        $this->notify($booking->face_id, $faceMessage, "/face/bookings/{$booking->uuid}", $booking->id);
    }

    private function notify(int $userId, string $message, string $url, int $bookingId): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => 'booking_dispute_settled',
                'data' => [
                    'message' => $message,
                    'booking_id' => $bookingId,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BookingDisputeSettled notification failed', [
                'booking_id' => $bookingId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
