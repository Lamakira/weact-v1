<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Jobs\SendWebPush;
use App\Models\Admin;
use App\Models\BookingMessage;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Rules\PushEndpointHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use NotificationChannels\WebPush\PushSubscription;
use Tests\TestCase;

class PushHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const FCM = 'https://fcm.googleapis.com/fcm/send/';

    protected function setUp(): void
    {
        parent::setUp();

        config(['webpush.vapid.public_key' => 'pub', 'webpush.vapid.private_key' => 'priv']);
        Cache::flush();
    }

    private function faceUser(array $attrs = []): User
    {
        $face = Face::factory()->create();

        return User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id] + $attrs);
    }

    private function producerUser(): User
    {
        $producer = Producer::factory()->create(['first_name' => 'Awa']);

        return User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
    }

    private function subscribed(User $user): User
    {
        $user->updatePushSubscription(self::FCM.'device-'.$user->id, 'k', 'a');

        return $user;
    }

    private function adminToken(): string
    {
        return Admin::factory()->create()->createToken('t', ['2fa'])->plainTextToken;
    }

    // --- H1: subscriptions die with the sessions ---------------------------------

    public function test_password_reset_removes_push_subscriptions(): void
    {
        $user = $this->subscribed($this->faceUser(['email' => 'reset@example.com', 'password' => Hash::make('oldpassword')]));

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => Password::createToken($user),
            'email' => 'reset@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_password_change_removes_push_subscriptions(): void
    {
        $user = $this->subscribed($this->faceUser(['password' => Hash::make('OldPassword1')]));

        $this->actingAs($user)->putJson('/api/v1/password', [
            'current_password' => 'OldPassword1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ])->assertOk();

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_admin_deactivating_a_face_removes_push_subscriptions(): void
    {
        $user = $this->subscribed($this->faceUser(['is_active' => true]));

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/admin/faces/{$user->userable->uuid}/toggle-active")
            ->assertOk();

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_admin_deactivating_a_producer_removes_push_subscriptions(): void
    {
        $user = $this->subscribed($this->producerUser());
        $user->update(['is_active' => true]);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/admin/producers/{$user->userable->uuid}/toggle-active")
            ->assertOk();

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_admin_deleting_a_face_or_producer_leaves_no_orphan_subscription(): void
    {
        $face = $this->subscribed($this->faceUser());
        $producer = $this->subscribed($this->producerUser());
        $token = $this->adminToken();

        $this->withToken($token)->deleteJson("/api/v1/admin/faces/{$face->userable->uuid}")->assertOk();
        $this->withToken($token)->deleteJson("/api/v1/admin/producers/{$producer->userable->uuid}")->assertOk();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_subscribing_requires_a_verified_email(): void
    {
        $user = $this->faceUser(['email_verified_at' => null]);

        $this->actingAs($user)
            ->postJson('/api/v1/me/push-subscriptions', [
                'endpoint' => self::FCM.'x',
                'keys' => ['p256dh' => 'p', 'auth' => 'a'],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');

        $this->assertSame(0, PushSubscription::count());
    }

    // --- M1: endpoint host allowlist ------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function endpoints(): array
    {
        return [
            'fcm' => ['https://fcm.googleapis.com/fcm/send/abc', true],
            'mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc', true],
            'mozilla subdomain' => ['https://eu.push.services.mozilla.com/x', true],
            'windows' => ['https://db5p.notify.windows.com/?token=abc', true],
            'apple' => ['https://web.push.apple.com/QAbc', true],
            'explicit 443' => ['https://fcm.googleapis.com:443/fcm/send/abc', true],
            'internal ip' => ['https://10.0.0.5/push', false],
            'metadata ip' => ['https://169.254.169.254/latest/meta-data', false],
            'localhost' => ['https://localhost/push', false],
            'other host' => ['https://push.example/abc', false],
            'suffix trick' => ['https://evilfcm.googleapis.com.attacker.test/x', false],
            'lookalike' => ['https://notfcm.googleapis.com.evil.io/x', false],
            'http' => ['http://fcm.googleapis.com/fcm/send/abc', false],
            'userinfo' => ['https://fcm.googleapis.com@evil.test/x', false],
            'userinfo 2' => ['https://user:pw@fcm.googleapis.com/x', false],
            'odd port' => ['https://fcm.googleapis.com:8443/x', false],
        ];
    }

    /**
     * @dataProvider endpoints
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoints')]
    public function test_endpoint_host_allowlist(string $endpoint, bool $allowed): void
    {
        $this->assertSame($allowed, PushEndpointHost::isAllowed($endpoint));

        $response = $this->actingAs($this->faceUser())->postJson('/api/v1/me/push-subscriptions', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'p', 'auth' => 'a'],
        ]);

        $allowed ? $response->assertCreated() : $response->assertStatus(422)->assertJsonValidationErrors(['endpoint']);
    }

    public function test_http_client_never_follows_redirects_and_has_short_timeouts(): void
    {
        $options = config('webpush.client_options');

        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(10, $options['timeout']);
        $this->assertSame(5, $options['connect_timeout']);
    }

    // --- M2: cap, job limits, no useless dispatch --------------------------------------

    public function test_subscriptions_are_capped_at_ten_per_user_pruning_the_oldest(): void
    {
        $user = $this->faceUser();

        foreach (range(1, 12) as $i) {
            $this->actingAs($user)->postJson('/api/v1/me/push-subscriptions', [
                'endpoint' => self::FCM."device-{$i}",
                'keys' => ['p256dh' => 'p', 'auth' => 'a'],
            ])->assertCreated();
            $this->travel(1)->seconds();
        }

        $endpoints = $user->pushSubscriptions()->pluck('endpoint')->all();
        $this->assertCount(10, $endpoints);
        $this->assertNotContains(self::FCM.'device-1', $endpoints);
        $this->assertNotContains(self::FCM.'device-2', $endpoints);
        $this->assertContains(self::FCM.'device-12', $endpoints);
    }

    public function test_the_cap_is_per_user(): void
    {
        $other = $this->subscribed($this->producerUser());
        $user = $this->faceUser();

        foreach (range(1, 11) as $i) {
            $user->updatePushSubscription(self::FCM."mine-{$i}", 'k', 'a');
        }
        $this->actingAs($user)->postJson('/api/v1/me/push-subscriptions', [
            'endpoint' => self::FCM.'mine-12',
            'keys' => ['p256dh' => 'p', 'auth' => 'a'],
        ])->assertCreated();

        $this->assertSame(10, $user->pushSubscriptions()->count());
        $this->assertSame(1, $other->pushSubscriptions()->count());
    }

    public function test_job_has_an_explicit_timeout_below_the_worker_and_fails_on_timeout(): void
    {
        $job = new SendWebPush(1, ['title' => 't', 'body' => 'b', 'url' => '/', 'tag' => 'x', 'ttl' => 1]);

        $this->assertLessThanOrEqual(60, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
    }

    public function test_no_job_is_dispatched_for_a_user_without_subscription(): void
    {
        Bus::fake([SendWebPush::class]);
        $user = $this->faceUser();

        Notification::create(['user_id' => $user->id, 'type' => 'booking_received', 'data' => ['message' => 'm', 'url' => '/x']]);

        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_distinct_events_on_the_same_url_get_distinct_tags(): void
    {
        Bus::fake([SendWebPush::class]);
        $user = $this->subscribed($this->faceUser());

        foreach (['un', 'deux'] as $message) {
            Notification::create(['user_id' => $user->id, 'type' => 'booking_received', 'data' => ['message' => $message, 'url' => '/face/bookings']]);
        }

        $tags = Bus::dispatched(SendWebPush::class)->map(fn (SendWebPush $j) => $j->payload['tag'])->all();
        $this->assertCount(2, array_unique($tags));
    }

    // --- afterCommit -----------------------------------------------------------------------

    public function test_no_push_is_queued_when_the_transaction_rolls_back(): void
    {
        config(['queue.default' => 'database']);
        $user = $this->subscribed($this->faceUser());
        DB::table('jobs')->delete();

        try {
            DB::transaction(function () use ($user): void {
                Notification::create(['user_id' => $user->id, 'type' => 'booking_received', 'data' => ['message' => 'm', 'url' => '/x']]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, DB::table('jobs')->where('payload', 'like', '%SendWebPush%')->count());

        DB::transaction(function () use ($user): void {
            Notification::create(['user_id' => $user->id, 'type' => 'booking_received', 'data' => ['message' => 'm', 'url' => '/x']]);
        });

        $this->assertSame(1, DB::table('jobs')->where('payload', 'like', '%SendWebPush%')->count());
    }

    // --- Booking chat messages ------------------------------------------------------------------

    /**
     * @return array{0: User, 1: User, 2: \App\Models\Booking}
     */
    private function bookingWithChat(): array
    {
        $face = $this->faceUser();
        $producer = $this->producerUser();
        $booking = \App\Models\Booking::factory()->create([
            'face_id' => $face->id,
            'producer_id' => $producer->id,
            'status' => \App\Enums\BookingStatus::Paid,
        ]);

        return [$face, $producer, $booking];
    }

    public function test_booking_chat_message_pushes_the_other_party_throttled(): void
    {
        Bus::fake([SendWebPush::class]);
        [$face, $producer, $booking] = $this->bookingWithChat();
        $this->subscribed($face);

        $this->actingAs($producer)->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['content' => 'Bonjour'])->assertCreated();
        $this->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['content' => 'Encore'])->assertCreated();

        Bus::assertDispatchedTimes(SendWebPush::class, 1);
        Bus::assertDispatched(SendWebPush::class, fn (SendWebPush $job): bool => $job->userId === $face->id
            && $job->payload['title'] === 'Nouveau message'
            && $job->payload['body'] === 'Awa: Bonjour'
            && $job->payload['url'] === "/face/bookings/{$booking->uuid}"
            && $job->payload['tag'] === "booking:{$booking->uuid}");

        $this->assertSame(2, BookingMessage::count());
    }

    public function test_booking_chat_push_goes_to_the_producer_with_the_producer_url(): void
    {
        Bus::fake([SendWebPush::class]);
        [$face, $producer, $booking] = $this->bookingWithChat();
        $this->subscribed($producer);

        $this->actingAs($face)->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['content' => 'Salut'])->assertCreated();

        Bus::assertDispatched(SendWebPush::class, fn (SendWebPush $job): bool => $job->userId === $producer->id
            && $job->payload['url'] === "/producer/bookings/{$booking->uuid}");
    }

    public function test_booking_chat_send_survives_a_push_failure(): void
    {
        [$face, $producer, $booking] = $this->bookingWithChat();
        $this->subscribed($face);
        $this->mock(\App\Services\Push\WebPushService::class, fn ($m) => $m->shouldReceive('queueForChatMessage')->andThrow(new \RuntimeException('push down')));

        $this->actingAs($producer)->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['content' => 'ok'])->assertCreated();
    }
}
