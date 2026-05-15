<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Events\FaceSubscriptionActivated;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: FaceSubscriptionActivated::class)]
final class NotifyFaceOnSubscriptionActivated
{
    public function handle(FaceSubscriptionActivated $event): void
    {
        try {
            $subscription = $event->subscription;
            $subscription->loadMissing('face.user');

            $faceUser = $subscription->face?->user;
            if (! $faceUser) {
                Log::warning('FaceSubscriptionActivated notification skipped — Face user missing', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                ]);

                return;
            }

            $expiresLabel = $subscription->expires_at?->locale('fr')->translatedFormat('d F Y') ?? 'la fin de l\'année';

            Notification::create([
                'user_id' => $faceUser->id,
                'type' => 'face_subscription_activated',
                'data' => [
                    'message' => "Votre abonnement Premium annuel est activé. Vos photos 3-4 et votre vidéo de jeu sont maintenant publiques jusqu'au {$expiresLabel}.",
                    'face_subscription_id' => $subscription->id,
                    'expires_at' => $subscription->expires_at?->toIso8601String(),
                    'url' => '/face/profile',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FaceSubscriptionActivated notification failed', [
                'face_subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
