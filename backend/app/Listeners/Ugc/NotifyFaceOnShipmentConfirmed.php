<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\ShipmentConfirmed;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ShipmentConfirmed::class)]
class NotifyFaceOnShipmentConfirmed
{
    /**
     * Notifie la Face que son produit est en route (numéro de suivi inclus).
     * Non-fatal : le shipment est déjà persisté.
     */
    public function handle(ShipmentConfirmed $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $shipment->owner;

            if ($owner instanceof Booking) {
                $owner->loadMissing('producer.userable');
                $faceUserId = $owner->face_id; // users.id (piège 2.4)
                $producerName = (string) data_get($owner, 'producer.userable.display_name', 'Le producteur');
                $productName = (string) $owner->nom_produit;
                $url = "/face/bookings/{$owner->uuid}";
            } elseif ($owner instanceof Candidature) {
                $owner->loadMissing('mission.producer');
                $mission = $owner->mission;
                $faceUserId = User::where('userable_type', Face::class)
                    ->where('userable_id', $owner->face_id) // faces.id (piège 2.4)
                    ->value('id');
                $producerName = (string) data_get($mission, 'producer.display_name', 'Le producteur');
                $productName = (string) ($mission->nom_produit ?? '');
                $url = $mission !== null ? "/face/missions/{$mission->uuid}" : '/face/missions';
            } else {
                return;
            }

            if (! $faceUserId) {
                return;
            }

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'ugc_shipment_confirmed',
                'data' => [
                    'message' => "{$producerName} a expédié votre produit « {$productName} » via {$shipment->transporteur} — numéro de suivi : {$shipment->numero_suivi}.",
                    'shipment_id' => $shipment->uuid, // identifiant public (parité ShipmentResource: id = uuid)
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ShipmentConfirmed notification failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
