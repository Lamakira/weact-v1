<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Events\FaceSubscriptionCancelled;
use App\Listeners\Subscription\NotifyFaceOnSubscriptionCancelled;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotifyFaceOnSubscriptionCancelledListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_creates_in_app_notification_with_correct_payload_for_cancelled_active_subscription(): void
    {
        $face = Face::factory()->create(['prenom' => 'Mariam']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->cancelled()->pro()->create([
            'face_id' => $face->id,
            'cancelled_at' => now(),
        ]);

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_cancelled')
            ->firstOrFail();

        $this->assertSame($subscription->id, $notification->data['face_subscription_id']);
        $this->assertNotNull($notification->data['cancelled_at']);
        $this->assertSame('/face/profile', $notification->data['url']);
        $this->assertStringContainsString('Pro', $notification->data['message']);
        $this->assertStringContainsString('annulé', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 4 d\'album', $notification->data['message']);
        $this->assertStringContainsString('redeviennent privées immédiatement', $notification->data['message']);
        $this->assertStringContainsString('support', $notification->data['message']);
        $this->assertStringNotContainsString('Premium annuel', $notification->data['message']);
        $this->assertStringNotContainsString('photos 3-4', $notification->data['message']);
    }

    public function test_listener_skips_when_face_user_is_missing(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $face->id,
        ]);

        $user->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionCancelled notification skipped — Face user missing'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription->fresh()));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_cancelled',
        ]);
    }

    public function test_listener_swallows_throwables_and_logs_warning(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $face->id,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionCancelled notification failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        Notification::creating(function (): void {
            throw new \RuntimeException('Forced failure for test');
        });

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_cancelled',
        ]);
    }

    public function test_listener_uses_pending_payment_copy_when_subscription_never_had_coverage(): void
    {
        $face = Face::factory()->create(['prenom' => 'Awa']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->cancelled()->starter()->create([
            'face_id' => $face->id,
            'starts_at' => null,
            'expires_at' => null,
            'cancelled_at' => now(),
        ]);

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_cancelled')
            ->firstOrFail();

        $this->assertSame($subscription->id, $notification->data['face_subscription_id']);
        $this->assertNotNull($notification->data['cancelled_at']);
        $this->assertSame('/face/profile', $notification->data['url']);
        $this->assertStringContainsString('Starter', $notification->data['message']);
        $this->assertStringContainsString('annulée avant activation', $notification->data['message']);
        $this->assertStringContainsString('Aucun avantage Premium', $notification->data['message']);
        $this->assertStringNotContainsString('redeviennent privées immédiatement', $notification->data['message']);
        $this->assertStringNotContainsString('2ème photo', $notification->data['message']);
    }

    public function test_listener_creates_starter_specific_message_for_cancelled_starter_active_subscription(): void
    {
        $face = Face::factory()->create(['prenom' => 'Cancelled Starter']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->cancelled()->starter()->create([
            'face_id' => $face->id,
            'cancelled_at' => now(),
        ]);

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_cancelled')
            ->firstOrFail();

        $this->assertStringContainsString('Starter', $notification->data['message']);
        $this->assertStringContainsString('2ème photo d\'album', $notification->data['message']);
        $this->assertStringContainsString('redeviennent privées immédiatement', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
        $this->assertStringNotContainsString('vidéo UGC', $notification->data['message']);
    }

    public function test_listener_creates_elite_specific_message_for_cancelled_elite_active_subscription(): void
    {
        $face = Face::factory()->create(['prenom' => 'Cancelled Elite']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->cancelled()->elite()->create([
            'face_id' => $face->id,
            'cancelled_at' => now(),
        ]);

        (new NotifyFaceOnSubscriptionCancelled)->handle(new FaceSubscriptionCancelled($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_cancelled')
            ->firstOrFail();

        $this->assertStringContainsString('Élite', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 6 d\'album', $notification->data['message']);
        $this->assertStringContainsString('vidéo UGC', $notification->data['message']);
        $this->assertStringContainsString('redeviennent privées immédiatement', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
        $this->assertStringNotContainsString('2ème photo', $notification->data['message']);
    }
}
