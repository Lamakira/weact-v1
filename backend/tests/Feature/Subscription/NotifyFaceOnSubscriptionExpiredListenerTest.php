<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Events\FaceSubscriptionExpired;
use App\Listeners\Subscription\NotifyFaceOnSubscriptionExpired;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotifyFaceOnSubscriptionExpiredListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_creates_in_app_notification_with_correct_payload(): void
    {
        $face = Face::factory()->create(['prenom' => 'Sékou']);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        (new NotifyFaceOnSubscriptionExpired)->handle(new FaceSubscriptionExpired($subscription));

        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'face_subscription_expired')
            ->firstOrFail();

        $this->assertSame($subscription->id, $notification->data['face_subscription_id']);
        $this->assertNotNull($notification->data['expired_at']);
        $this->assertSame('/face/profile', $notification->data['url']);
        $this->assertStringContainsString('expiré', $notification->data['message']);
        $this->assertStringContainsString('photos 3-4', $notification->data['message']);
        $this->assertStringContainsString('vidéo de jeu', $notification->data['message']);
        $this->assertStringContainsString('Renouvelez', $notification->data['message']);
    }

    public function test_listener_skips_when_face_user_is_missing(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $face->id,
        ]);

        $user->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionExpired notification skipped — Face user missing'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        (new NotifyFaceOnSubscriptionExpired)->handle(new FaceSubscriptionExpired($subscription->fresh()));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_expired',
        ]);
    }

    public function test_listener_swallows_throwables_and_logs_warning(): void
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
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceSubscriptionExpired notification failed'
                && ($context['face_subscription_id'] ?? null) === $subscription->id);

        Notification::creating(function (): void {
            throw new \RuntimeException('Forced failure for test');
        });

        (new NotifyFaceOnSubscriptionExpired)->handle(new FaceSubscriptionExpired($subscription));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'face_subscription_expired',
        ]);
    }
}
