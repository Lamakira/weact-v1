<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Events\FaceSubscriptionActivated;
use App\Listeners\Subscription\NotifyFaceOnSubscriptionActivated;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotifyFaceOnSubscriptionActivatedListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_creates_in_app_notification_with_correct_payload(): void
    {
        $face = Face::factory()->create(['prenom' => 'Aïcha']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addYear(),
        ]);

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_activated')
            ->firstOrFail();

        $this->assertSame($subscription->id, $notification->data['face_subscription_id']);
        $this->assertNotNull($notification->data['expires_at']);
        $this->assertSame('/face/profile', $notification->data['url']);
        $this->assertStringContainsString('Pro', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 4 d\'album', $notification->data['message']);
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
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
        ]);

        $user->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionActivated notification skipped — Face user missing'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription->fresh()));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_activated',
        ]);
    }

    public function test_listener_swallows_throwables_and_logs_warning(): void
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
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionActivated notification failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        // Force throwable: register listener that throws on Notification creating
        Notification::creating(function (): void {
            throw new \RuntimeException('Forced failure for test');
        });

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_activated',
        ]);
    }

    public function test_listener_formats_expires_at_in_french(): void
    {
        $face = Face::factory()->create(['prenom' => 'Test FR']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->pro()->create([
            'face_id' => $face->id,
            'expires_at' => now()->setDate(2027, 5, 15)->setTime(10, 0),
        ]);

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_activated')
            ->firstOrFail();

        $this->assertStringContainsString('15 mai 2027', $notification->data['message']);
        $this->assertStringContainsString('Pro', $notification->data['message']);
    }

    public function test_listener_creates_starter_specific_message_for_starter_subscription(): void
    {
        $face = Face::factory()->create(['prenom' => 'Starter Face']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->starter()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addYear(),
        ]);

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_activated')
            ->firstOrFail();

        $this->assertStringContainsString('Starter', $notification->data['message']);
        $this->assertStringContainsString('2ème photo d\'album', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
        $this->assertStringNotContainsString('vidéo UGC', $notification->data['message']);
    }

    public function test_listener_creates_elite_specific_message_for_elite_subscription(): void
    {
        $face = Face::factory()->create(['prenom' => 'Elite Face']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->active()->elite()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addYear(),
        ]);

        (new NotifyFaceOnSubscriptionActivated)->handle(new FaceSubscriptionActivated($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_activated')
            ->firstOrFail();

        $this->assertStringContainsString('Élite', $notification->data['message']);
        $this->assertStringContainsString('photos 2 à 6 d\'album', $notification->data['message']);
        $this->assertStringContainsString('vidéo UGC', $notification->data['message']);
        $this->assertStringNotContainsString('photos 2 à 4', $notification->data['message']);
        $this->assertStringNotContainsString('2ème photo', $notification->data['message']);
    }
}
