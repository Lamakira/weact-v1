<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Events\FaceSubscriptionCancelled;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: FaceSubscriptionCancelled::class)]
final class NotifyFaceOnSubscriptionCancelled
{
    public function handle(FaceSubscriptionCancelled $event): void
    {
        try {
            $subscription = $event->subscription;
            $subscription->loadMissing('face.user');

            $faceUser = $subscription->face->user;
            if (! $faceUser) {
                Log::warning('FaceSubscriptionCancelled notification skipped — Face user missing', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                ]);

                return;
            }

            $hadCoverage = $subscription->starts_at !== null && $subscription->expires_at !== null;
            $planLabel = $subscription->plan->label();
            $message = $hadCoverage
                ? "Votre abonnement {$planLabel} a été annulé. ".ucfirst($subscription->plan->premiumMediaSummary())." redeviennent privées immédiatement. Contactez le support si vous pensez qu'il s'agit d'une erreur."
                : "Votre demande d'abonnement {$planLabel} a été annulée avant activation. Aucun avantage Premium n'a été activé. Contactez le support si vous pensez qu'il s'agit d'une erreur.";

            Notification::create([
                'user_id' => $faceUser->id,
                'type' => 'face_subscription_cancelled',
                'data' => [
                    'message' => $message,
                    'face_subscription_id' => $subscription->id,
                    'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                    'url' => '/face/profile',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FaceSubscriptionCancelled notification failed', [
                'face_subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
