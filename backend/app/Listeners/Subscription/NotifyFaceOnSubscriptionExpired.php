<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Events\FaceSubscriptionExpired;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: FaceSubscriptionExpired::class)]
final class NotifyFaceOnSubscriptionExpired
{
    public function handle(FaceSubscriptionExpired $event): void
    {
        try {
            $subscription = $event->subscription;
            $subscription->loadMissing('face.user');

            $faceUser = $subscription->face?->user;
            if (! $faceUser) {
                Log::warning('FaceSubscriptionExpired notification skipped — Face user missing', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                ]);

                return;
            }

            Notification::create([
                'user_id' => $faceUser->id,
                'type' => 'face_subscription_expired',
                'data' => [
                    'message' => 'Votre abonnement Premium annuel a expiré. Vos photos 3-4 et votre vidéo de jeu sont à nouveau cachées au public. Renouvelez pour les rendre visibles à nouveau.',
                    'face_subscription_id' => $subscription->id,
                    'expired_at' => $subscription->expires_at?->toIso8601String(),
                    'url' => '/face/profile',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FaceSubscriptionExpired notification failed', [
                'face_subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
