<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\PushSubscription;
use Tests\TestCase;

class PushSubscriptionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/device-one';

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webpush.vapid.public_key' => 'public-key-test',
            'webpush.vapid.private_key' => 'private-key-test',
        ]);

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $endpoint = self::ENDPOINT): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'p256dh-value', 'auth' => 'auth-value'],
            'content_encoding' => 'aes128gcm',
        ];
    }

    public function test_store_creates_a_subscription_for_the_user(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/me/push-subscriptions', $this->body())
            ->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => User::class,
            'subscribable_id' => $this->faceUser->id,
            'endpoint' => self::ENDPOINT,
            'public_key' => 'p256dh-value',
            'auth_token' => 'auth-value',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_store_is_an_upsert_by_endpoint(): void
    {
        $this->actingAs($this->faceUser)->postJson('/api/v1/me/push-subscriptions', $this->body())->assertCreated();

        $rotated = $this->body();
        $rotated['keys'] = ['p256dh' => 'rotated-p256dh', 'auth' => 'rotated-auth'];
        $this->postJson('/api/v1/me/push-subscriptions', $rotated)->assertCreated();

        $this->assertSame(1, PushSubscription::count());
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => self::ENDPOINT, 'public_key' => 'rotated-p256dh']);
    }

    public function test_store_reattaches_an_endpoint_owned_by_another_user(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $producerUser->updatePushSubscription(self::ENDPOINT, 'old-key', 'old-auth');

        $this->actingAs($this->faceUser)->postJson('/api/v1/me/push-subscriptions', $this->body())->assertCreated();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame(0, $producerUser->pushSubscriptions()->count());
        $this->assertSame(1, $this->faceUser->pushSubscriptions()->count());
    }

    public function test_store_defaults_content_encoding_to_aes128gcm(): void
    {
        $body = $this->body();
        unset($body['content_encoding']);

        $this->actingAs($this->faceUser)->postJson('/api/v1/me/push-subscriptions', $body)->assertCreated();

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => self::ENDPOINT, 'content_encoding' => 'aes128gcm']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing endpoint' => [['keys' => ['p256dh' => 'a', 'auth' => 'b']], 'endpoint'],
            'http endpoint' => [['endpoint' => 'http://insecure.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']], 'endpoint'],
            'not a url' => [['endpoint' => 'nope', 'keys' => ['p256dh' => 'a', 'auth' => 'b']], 'endpoint'],
            'missing keys' => [['endpoint' => self::ENDPOINT], 'keys'],
            'missing auth' => [['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'a']], 'keys.auth'],
            'missing p256dh' => [['endpoint' => self::ENDPOINT, 'keys' => ['auth' => 'a']], 'keys.p256dh'],
            'bad encoding' => [['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'a', 'auth' => 'b'], 'content_encoding' => 'rot13'], 'content_encoding'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @dataProvider invalidPayloads
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function test_store_validates_the_payload(array $payload, string $field): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/me/push-subscriptions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_destroy_removes_only_the_callers_subscription(): void
    {
        $this->faceUser->updatePushSubscription(self::ENDPOINT, 'k', 'a');
        $this->faceUser->updatePushSubscription('https://updates.push.services.mozilla.com/other', 'k', 'a');

        $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => self::ENDPOINT]);
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_destroy_does_not_touch_another_users_subscription(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $producerUser->updatePushSubscription(self::ENDPOINT, 'k', 'a');

        $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => self::ENDPOINT])
            ->assertOk();

        $this->assertSame(1, $producerUser->pushSubscriptions()->count());
    }

    public function test_destroy_requires_an_endpoint(): void
    {
        $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/me/push-subscriptions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint']);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/me/push-subscriptions', $this->body())->assertUnauthorized();
        $this->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => self::ENDPOINT])->assertUnauthorized();
        $this->getJson('/api/v1/push/public-key')->assertUnauthorized();
    }

    public function test_admins_are_refused(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin)->postJson('/api/v1/me/push-subscriptions', $this->body())->assertForbidden();
        $this->deleteJson('/api/v1/me/push-subscriptions', ['endpoint' => self::ENDPOINT])->assertForbidden();
        $this->getJson('/api/v1/push/public-key')->assertForbidden();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_public_key_is_exposed_when_configured(): void
    {
        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'public-key-test');
    }

    public function test_public_key_is_null_when_keys_are_missing(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.public_key', null);
    }

    public function test_account_deletion_removes_all_subscriptions(): void
    {
        $this->faceUser->forceFill(['password' => bcrypt('secret-pass-123')])->save();
        $this->faceUser->updatePushSubscription(self::ENDPOINT, 'k', 'a');
        $this->faceUser->updatePushSubscription('https://updates.push.services.mozilla.com/other', 'k', 'a');

        $this->actingAs($this->faceUser)
            ->deleteJson('/api/v1/user/account', ['password' => 'secret-pass-123'])
            ->assertOk();

        $this->assertSame(0, PushSubscription::count());
    }
}
