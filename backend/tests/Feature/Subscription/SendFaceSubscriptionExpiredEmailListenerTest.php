<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Events\FaceSubscriptionExpired;
use App\Listeners\Subscription\SendFaceSubscriptionExpiredEmail;
use App\Mail\FaceSubscriptionExpiredMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendFaceSubscriptionExpiredEmailListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_queues_face_subscription_expired_mail(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Bintou']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'expired@example.test',
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        (new SendFaceSubscriptionExpiredEmail)->handle(new FaceSubscriptionExpired($subscription));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            FaceSubscriptionExpiredMail::class,
            fn (FaceSubscriptionExpiredMail $mail): bool => $mail->hasTo('expired@example.test')
                && $mail->face->id === $face->id
                && $mail->subscription->id === $subscription->id,
        );
    }

    public function test_listener_skips_when_face_user_is_missing(): void
    {
        Mail::fake();

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        $faceUser->delete();

        (new SendFaceSubscriptionExpiredEmail)->handle(new FaceSubscriptionExpired($subscription->fresh()));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_face_email_is_empty(): void
    {
        Mail::fake();

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => '',
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        (new SendFaceSubscriptionExpiredEmail)->handle(new FaceSubscriptionExpired($subscription));

        Mail::assertNothingQueued();
    }

    public function test_listener_does_not_throw_when_mailer_fails(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionExpiredMail queue failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailer down'));

        (new SendFaceSubscriptionExpiredEmail)->handle(new FaceSubscriptionExpired($subscription));
    }

    public function test_listener_does_not_throw_when_queue_serialization_fails(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionExpiredMail queue failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        $pendingMail = \Mockery::mock();
        $pendingMail->shouldReceive('queue')->andThrow(new \RuntimeException('Queue connection down'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        (new SendFaceSubscriptionExpiredEmail)->handle(new FaceSubscriptionExpired($subscription));
    }
}
