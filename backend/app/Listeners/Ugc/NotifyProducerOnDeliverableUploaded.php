<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableUploaded;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DeliverableUploaded::class)]
class NotifyProducerOnDeliverableUploaded
{
    /**
     * Notifie le Producteur qu'une vidéo Unboxing a été déposée — à valider.
     * Non-fatal : l'upload est déjà persisté, une notif ratée ne doit pas
     * faire échouer le flux (try/catch + Log::warning, calque
     * NotifyProducerOnProductReceived ; leçon webhook no-throw + post-commit).
     */
    public function handle(DeliverableUploaded $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $deliverable->owner;

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
                'type' => 'ugc_deliverable_uploaded',
                'data' => [
                    'message' => "Une nouvelle vidéo Unboxing a été déposée pour « {$productName} » — à valider sous 48 h",
                    'deliverable_id' => $deliverable->uuid,
                    'kind' => $deliverable->kind->value,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeliverableUploaded notification failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
