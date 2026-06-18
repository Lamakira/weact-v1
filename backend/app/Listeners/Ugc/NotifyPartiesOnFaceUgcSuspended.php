<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\FaceUgcSuspended;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifie les deux parties à la suspension UGC d'une Face (épic 5, story 5.1).
 * SYNCHRONE (PAS ShouldQueue) : appelé après commit depuis le cron, dans le
 * process. Calque NotifyPartiesOnBookingCompleted : chaque notif est enrobée
 * indépendamment (un échec n'empêche pas l'autre), Log::warning sur échec.
 *
 * - Producteur : TOUJOURS notifié (deal non livré + proposez un remplacement),
 *   pour les deux types d'owner (booking → producer users.id ; candidature →
 *   mission.producer.user.id).
 * - Face : seulement à la PREMIÈRE suspension ($event->faceNewlySuspended) — un
 *   2ᵉ deal mort d'une Face déjà suspendue ne la re-notifie pas.
 */
#[AsEventListener(event: FaceUgcSuspended::class)]
class NotifyPartiesOnFaceUgcSuspended
{
    public function handle(FaceUgcSuspended $event): void
    {
        $shipment = $event->shipment;
        $owner = $shipment->owner;

        if ($owner instanceof Booking) {
            $producerUserId = $owner->producer_id;          // users.id direct
            $faceUserId = $owner->face_id;                  // users.id (piège 2.4)
            $productName = (string) $owner->nom_produit;
            $producerUrl = "/producer/bookings/{$owner->uuid}";
            $faceUrl = "/face/bookings/{$owner->uuid}";
        } elseif ($owner instanceof Candidature) {
            $owner->loadMissing('mission.producer.user');
            $mission = $owner->mission;
            // ->getKey() (pas ->id) : Producer::user() est un morphOne → Larastan
            // résout le type à Model (calque UgcRefundService:191).
            $producerUserId = $mission->producer?->user?->getKey();
            $faceUserId = User::where('userable_type', Face::class)
                ->where('userable_id', $owner->face_id)     // faces.id (piège 2.4)
                ->value('id');
            $productName = (string) ($mission->nom_produit ?? '');
            $producerUrl = $mission !== null ? "/producer/missions/{$mission->uuid}" : '/producer/missions';
            $faceUrl = $mission !== null ? "/face/missions/{$mission->uuid}" : '/face/missions';
        } else {
            return;
        }

        // Producteur : toujours notifié (si résolu).
        if ($producerUserId) {
            try {
                Notification::create([
                    'user_id' => $producerUserId,
                    'type' => 'ugc_face_suspended',
                    'data' => [
                        'message' => "La Face n'a pas livré « {$productName} » dans les délais : son compte est suspendu. Vous avez été remboursé(e) le cas échéant ; vous pouvez proposer un remplacement.",
                        'shipment_id' => $shipment->uuid,
                        'url' => $producerUrl,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('FaceUgcSuspended notification for Producer failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Face : seulement à la première suspension de la Face (garde anti-doublon).
        if ($event->faceNewlySuspended && $faceUserId) {
            try {
                Notification::create([
                    'user_id' => $faceUserId,
                    'type' => 'ugc_account_suspended',
                    'data' => [
                        'message' => 'Ton compte UGC est suspendu pour livrable manquant. Tu peux régulariser depuis ta page de suspension.',
                        'shipment_id' => $shipment->uuid,
                        'url' => $faceUrl,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('FaceUgcSuspended notification for Face failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
