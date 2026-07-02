<?php

declare(strict_types=1);

namespace App\Listeners\Mission;

use App\Events\UgcMissionDealAccepted;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: UgcMissionDealAccepted::class)]
class NotifyProducerOnUgcDealAccepted
{
    /**
     * Notifie le Producteur que la Face s'est engagée sur le deal UGC.
     * Non-fatal : la candidature est déjà persistée.
     */
    public function handle(UgcMissionDealAccepted $event): void
    {
        try {
            $candidature = $event->candidature;
            $candidature->loadMissing(['mission', 'face']);
            $mission = $candidature->mission;

            $producerUser = User::where('userable_type', Producer::class)
                ->where('userable_id', $mission->producer_id)
                ->first();

            if (! $producerUser) {
                return;
            }

            Notification::create([
                'user_id' => $producerUser->id,
                'type' => 'ugc_deal_accepted',
                'data' => [
                    'message' => $candidature->face->prenom.' a accepté votre mission UGC "'.$mission->titre.'" — préparez l\'expédition du produit.',
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->id}/candidatures",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UgcMissionDealAccepted notification failed', [
                'candidature_id' => $event->candidature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
