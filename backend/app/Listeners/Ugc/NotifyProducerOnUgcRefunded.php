<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\UgcCommissionRefunded;
use App\Models\Mission;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UgcCommissionRefunded::class)]
class NotifyProducerOnUgcRefunded
{
    /**
     * Notifie le Producteur que sa commission a été créditée sur son
     * portefeuille WeAct (settlement wallet synchrone — story 2.6). Non-fatal :
     * commission_refunded_at + le crédit wallet sont déjà persistés.
     */
    public function handle(UgcCommissionRefunded $event): void
    {
        try {
            $owner = $event->owner;

            // bookings.producer_id EST un users.id ; missions.producer_id est un
            // producers.id → User via la relation Producer::user() (morphOne userable).
            $producerUserId = $owner instanceof Mission
                ? $owner->producer?->user?->getKey()
                : $owner->producer_id;

            if ($producerUserId === null) {
                return;
            }

            $amount = number_format((int) $owner->commission_ugc, 0, ',', ' ');

            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'ugc_commission_refunded',
                'data' => [
                    'message' => "Votre commission UGC de {$amount} FCFA a été créditée sur votre portefeuille WeAct.",
                    'url' => '/producer/wallet',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UgcCommissionRefunded notification failed', [
                'owner_id' => $event->owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
