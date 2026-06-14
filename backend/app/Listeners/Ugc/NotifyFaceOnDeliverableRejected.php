<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\DeliverableRejected;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DeliverableRejected::class)]
class NotifyFaceOnDeliverableRejected
{
    /**
     * Notifie la Face que son livrable a été refusé, avec le motif (review_note).
     * La fenêtre d'upload du même kind est rouverte (chrono conservé, D-4.3.b).
     * Non-fatal : le rejet est déjà persisté (try/catch + Log::warning).
     */
    public function handle(DeliverableRejected $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $deliverable->owner;

            if ($owner instanceof Booking) {
                $faceUserId = $owner->face_id; // users.id (piège FK 2.4)
                $url = "/face/bookings/{$owner->uuid}";
            } elseif ($owner instanceof Candidature) {
                $owner->loadMissing('mission');
                $mission = $owner->mission;

                if ($mission === null) {
                    return;
                }

                $faceUserId = User::where('userable_type', Face::class)
                    ->where('userable_id', $owner->face_id) // faces.id (piège FK 2.4)
                    ->value('id');
                $url = "/face/missions/{$mission->uuid}";
            } else {
                return;
            }

            if (! $faceUserId) {
                return;
            }

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'ugc_deliverable_rejected',
                'data' => [
                    'message' => "Ton {$deliverable->kind->label()} a été refusé : {$deliverable->review_note}. Dépose une nouvelle version.",
                    'deliverable_id' => $deliverable->uuid,
                    'kind' => $deliverable->kind->value,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeliverableRejected notification failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
