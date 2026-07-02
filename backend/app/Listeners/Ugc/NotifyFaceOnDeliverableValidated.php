<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Enums\DeliverableKind;
use App\Events\DeliverableValidated;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: DeliverableValidated::class)]
class NotifyFaceOnDeliverableValidated
{
    /**
     * Notifie la Face que son livrable est validé. Message kind-aware : Unboxing
     * validé → « dépose ton Avis (14 j) » ; Avis validé → « deal terminé ».
     * Non-fatal : la validation est déjà persistée (try/catch + Log::warning).
     */
    public function handle(DeliverableValidated $event): void
    {
        try {
            $deliverable = $event->deliverable;
            $owner = $deliverable->owner;

            if ($owner instanceof Booking) {
                $faceUserId = $owner->face_id; // users.id (piège FK 2.4)
                $productName = (string) $owner->nom_produit;
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
                $productName = (string) $mission->nom_produit;
                $url = "/face/missions/{$mission->uuid}";
            } else {
                return;
            }

            if (! $faceUserId) {
                return;
            }

            $message = $deliverable->kind === DeliverableKind::Unboxing
                ? "Ton Unboxing « {$productName} » est validé — tu peux maintenant déposer ton Avis (14 jours)."
                : "Ton deal UGC « {$productName} » est terminé — bravo, ton Avis est validé !";

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'ugc_deliverable_validated',
                'data' => [
                    'message' => $message,
                    'deliverable_id' => $deliverable->uuid,
                    'kind' => $deliverable->kind->value,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeliverableValidated notification failed', [
                'deliverable_id' => $event->deliverable->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
