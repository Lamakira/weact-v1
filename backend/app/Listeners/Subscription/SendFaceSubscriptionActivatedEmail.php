<?php

declare(strict_types=1);

namespace App\Listeners\Subscription;

use App\Events\FaceSubscriptionActivated;
use App\Mail\FaceSubscriptionActivatedMail;
use App\Models\Face;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: FaceSubscriptionActivated::class)]
final class SendFaceSubscriptionActivatedEmail
{
    public function handle(FaceSubscriptionActivated $event): void
    {
        try {
            $subscription = $event->subscription;
            $subscription->loadMissing('face.user');

            $face = $subscription->face;
            if (! $face instanceof Face) {
                Log::warning('FaceSubscriptionActivatedMail skipped — face missing', [
                    'face_subscription_id' => $subscription->id,
                ]);

                return;
            }

            /** @var User|null $faceUser */
            $faceUser = $face->user;
            if (! $faceUser) {
                return;
            }

            $faceEmail = trim((string) $faceUser->email);
            if ($faceEmail === '') {
                return;
            }

            Mail::to($faceEmail)->queue(new FaceSubscriptionActivatedMail(
                face: $face,
                subscription: $subscription,
            ));
        } catch (\Throwable $e) {
            Log::warning('FaceSubscriptionActivatedMail queue failed', [
                'face_subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
