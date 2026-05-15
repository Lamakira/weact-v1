<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Events\FaceSubscriptionActivated;
use App\Listeners\Subscription\SendFaceSubscriptionActivatedEmail;
use App\Mail\FaceSubscriptionActivatedMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendFaceSubscriptionActivatedEmailListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_queues_face_subscription_activated_mail(): void
    {
        Mail::fake();

        $face = Face::factory()->create(['prenom' => 'Aminata']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'activate@example.test',
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'paid_amount' => 50000,
        ]);

        (new SendFaceSubscriptionActivatedEmail)->handle(new FaceSubscriptionActivated($subscription));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            FaceSubscriptionActivatedMail::class,
            fn (FaceSubscriptionActivatedMail $mail): bool => $mail->hasTo('activate@example.test')
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
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        $faceUser->delete();

        (new SendFaceSubscriptionActivatedEmail)->handle(new FaceSubscriptionActivated($subscription->fresh()));

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
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        (new SendFaceSubscriptionActivatedEmail)->handle(new FaceSubscriptionActivated($subscription));

        Mail::assertNothingQueued();
    }

    public function test_listener_does_not_throw_when_mailer_fails(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionActivatedMail queue failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailer down'));

        (new SendFaceSubscriptionActivatedEmail)->handle(new FaceSubscriptionActivated($subscription));
    }

    public function test_listener_does_not_throw_when_queue_serialization_fails(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionActivatedMail queue failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        $pendingMail = \Mockery::mock();
        $pendingMail->shouldReceive('queue')->andThrow(new \RuntimeException('Queue connection down'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        (new SendFaceSubscriptionActivatedEmail)->handle(new FaceSubscriptionActivated($subscription));
    }
}
