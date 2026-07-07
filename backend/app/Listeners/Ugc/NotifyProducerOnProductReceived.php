<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\ProductReceived;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ProductReceived::class)]
class NotifyProducerOnProductReceived
{
    /**
     * Notifie le Producteur que la Face a reçu le produit et que le chrono
     * Unboxing démarre. Non-fatal : la transition est déjà persistée.
     */
    public function handle(ProductReceived $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $shipment->owner;
            $days = (int) config('ugc.deliverable_days.unboxing', 7);
            // Preuve « produit reçu » (spec réception) : la Face a joint des
            // photos ; le compteur les signale au Producteur (destinataire de la
            // preuve). 0 pour les shipments pré-deploy (rétrocompat).
            $photosCount = $shipment->receptionPhotos()->count();
            $photosPhrase = $photosCount > 0
                ? " {$photosCount} photo".($photosCount > 1 ? 's' : '').' du produit reçu jointe'.($photosCount > 1 ? 's' : '').'.'
                : '';

            if ($owner instanceof Booking) {
                $producerUserId = $owner->producer_id; // users.id (piège FK n°1)
                $productName = (string) $owner->nom_produit;
                $url = "/producer/bookings/{$owner->uuid}";
            } elseif ($owner instanceof Candidature) {
                $owner->loadMissing('mission');
                $mission = $owner->mission;

                if ($mission === null) {
                    return;
                }

                $producerUserId = User::where('userable_type', Producer::class)
                    ->where('userable_id', $mission->producer_id) // producers.id
                    ->value('id');
                $productName = (string) $mission->nom_produit;
                $url = "/producer/missions/{$mission->uuid}/candidatures";
            } else {
                return;
            }

            if (! $producerUserId) {
                return;
            }

            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'ugc_product_received',
                'data' => [
                    'message' => "{$shipment->destinataire_nom} a confirmé la réception de « {$productName} » — le chrono Unboxing ({$days} jours) démarre.{$photosPhrase}",
                    'shipment_id' => $shipment->uuid,
                    'reception_photos_count' => $photosCount,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProductReceived notification failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
