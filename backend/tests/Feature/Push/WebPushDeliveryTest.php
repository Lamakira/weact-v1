<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Jobs\SendWebPush;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery;
use Mockery\MockInterface;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webpush.vapid.public_key' => 'public-key-test',
            'webpush.vapid.private_key' => 'private-key-test',
        ]);

        $this->user = $this->makeUser();
        $this->other = $this->makeUser();
    }

    private function makeUser(): User
    {
        $face = Face::factory()->create();

        return User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, string $type, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'data' => $data + ['message' => 'Un message de test', 'url' => '/face/bookings/abc'],
        ]);
    }

    /** Dispatch is skipped for users without any device. */
    private function withDevice(): void
    {
        $this->user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/base', 'k', 'a');
    }

    // --- Dispatch from the Notification observer ---------------------------------

    public function test_allowlisted_type_queues_a_push_for_the_notifications_user_only(): void
    {
        $this->withDevice();
        Bus::fake([SendWebPush::class]);

        $this->notify($this->user, 'booking_received');

        Bus::assertDispatched(SendWebPush::class, function (SendWebPush $job): bool {
            return $job->userId === $this->user->id
                && $job->payload['title'] === 'Booking'
                && $job->payload['body'] === 'Un message de test'
                && $job->payload['url'] === '/face/bookings/abc'
                && str_starts_with($job->payload['tag'], 'booking_received:')
                && $job->payload['tag'] !== 'booking_received:'
                && $job->afterCommit === true;
        });
        Bus::assertDispatchedTimes(SendWebPush::class, 1);
    }

    public function test_excluded_type_queues_nothing(): void
    {
        $this->withDevice();
        Bus::fake([SendWebPush::class]);

        $this->notify($this->user, 'booking_rating_received');
        $this->notify($this->user, 'some_unknown_type');

        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_nothing_is_queued_when_vapid_keys_are_missing(): void
    {
        $this->withDevice();
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        Bus::fake([SendWebPush::class]);

        $this->notify($this->user, 'booking_received');

        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_payload_truncates_the_body_and_sanitises_the_url(): void
    {
        $this->withDevice();
        Bus::fake([SendWebPush::class]);

        $this->notify($this->user, 'booking_paid', ['message' => str_repeat('a', 400), 'url' => 'https://evil.example/x']);
        $this->notify($this->user, 'booking_paid', ['url' => '//evil.example/x']);

        $jobs = Bus::dispatched(SendWebPush::class);
        $this->assertCount(2, $jobs);
        $this->assertSame('Paiement', $jobs[0]->payload['title']);
        $this->assertLessThanOrEqual(140, mb_strlen($jobs[0]->payload['body']));
        $this->assertSame('/', $jobs[0]->payload['url']);
        $this->assertSame('/', $jobs[1]->payload['url']);
    }

    public function test_notification_creation_survives_a_dispatch_failure(): void
    {
        $this->withDevice();
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue down'));
        Log::spy();

        $notification = $this->notify($this->user, 'booking_received');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    // --- Delivery (job) ------------------------------------------------------------

    /**
     * Bind a channel whose WebPush client is a mock: no network.
     *
     * @return MockInterface&WebPush
     */
    private function fakeSender(callable $reports): MockInterface
    {
        /** @var MockInterface&WebPush $webPush */
        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('flush')->andReturnUsing($reports);

        $this->app->instance(
            WebPushChannel::class,
            new WebPushChannel($webPush, new ReportHandler($this->app->make(Dispatcher::class))),
        );

        return $webPush;
    }

    private function report(string $endpoint, int $status): MessageSentReport
    {
        return new MessageSentReport(
            new PsrRequest('POST', $endpoint),
            new PsrResponse($status),
            $status < 300,
            $status < 300 ? 'OK' : 'Gone',
        );
    }

    public function test_job_sends_one_push_per_subscription_of_the_user_only(): void
    {
        $this->user->updatePushSubscription('https://push.example/a', 'k1', 'a1');
        $this->user->updatePushSubscription('https://push.example/b', 'k2', 'a2');
        $this->other->updatePushSubscription('https://push.example/other', 'k3', 'a3');

        $queued = [];
        $webPush = $this->fakeSender(function () use (&$queued) {
            foreach ($queued as $endpoint) {
                yield $this->report($endpoint, 201);
            }
        });
        $webPush->shouldReceive('queueNotification')->andReturnUsing(function (Subscription $s, ?string $payload) use (&$queued): void {
            $queued[] = $s->getEndpoint();
            $decoded = json_decode((string) $payload, true);
            $this->assertSame('Booking', $decoded['title']);
            $this->assertSame('Un message de test', $decoded['body']);
            $this->assertSame('/face/bookings/abc', $decoded['data']['url']);
        });

        $this->notify($this->user, 'booking_received');

        $this->assertEqualsCanonicalizing(['https://push.example/a', 'https://push.example/b'], $queued);
    }

    public function test_expired_subscriptions_are_deleted(): void
    {
        $this->user->updatePushSubscription('https://push.example/alive', 'k1', 'a1');
        $this->user->updatePushSubscription('https://push.example/gone', 'k2', 'a2');

        $webPush = $this->fakeSender(function () {
            yield $this->report('https://push.example/alive', 201);
            yield $this->report('https://push.example/gone', 410);
        });
        $webPush->shouldReceive('queueNotification');

        $this->notify($this->user, 'booking_received');

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example/alive']);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/gone']);
    }

    public function test_nothing_is_sent_without_subscription(): void
    {
        $webPush = $this->fakeSender(fn () => yield from []);
        $webPush->shouldNotReceive('queueNotification');

        $this->notify($this->user, 'booking_received');

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_failing_sender_never_breaks_notification_creation(): void
    {
        $this->user->updatePushSubscription('https://push.example/a', 'k1', 'a1');

        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->andThrow(new \RuntimeException('push service exploded'));
        $this->app->instance(
            WebPushChannel::class,
            new WebPushChannel($webPush, new ReportHandler($this->app->make(Dispatcher::class))),
        );
        Log::spy();

        $notification = $this->notify($this->user, 'booking_received');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'Web push failed')->once();
    }
}
