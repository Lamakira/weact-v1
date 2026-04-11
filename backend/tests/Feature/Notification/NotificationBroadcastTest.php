<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Events\NotificationCreated;
use App\Http\Resources\NotificationResource;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
    }

    public function test_creating_notification_dispatches_notification_created_event(): void
    {
        Event::fake([NotificationCreated::class]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => 'candidature_accepted',
            'data' => ['message' => 'Votre candidature a été acceptée'],
        ]);

        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) {
            return $event->userId === $this->user->id
                && $event->payload['type'] === 'candidature_accepted';
        });
    }

    public function test_notification_created_broadcasts_on_correct_private_channel(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'candidature_accepted',
            'data' => ['message' => 'Votre candidature a été acceptée'],
        ]);

        $event = new NotificationCreated($notification);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

        $expectedChannel = new PrivateChannel("App.Models.User.{$this->user->id}");
        $this->assertEquals($expectedChannel->name, $channels[0]->name);
    }

    public function test_broadcast_payload_matches_notification_resource_structure(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'booking_confirmed',
            'data' => [
                'message' => 'Booking confirmé',
                'booking_id' => 42,
            ],
            'read_at' => now()->subMinute(),
        ]);

        $event = new NotificationCreated($notification);
        $payload = $event->broadcastWith();
        $resourcePayload = (new NotificationResource($notification))->toArray(request());

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('read_at', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertSame($resourcePayload, $payload);

        $notification->update([
            'read_at' => now(),
            'data' => ['message' => 'Notification mutated after dispatch'],
        ]);

        $this->assertSame($resourcePayload, $event->broadcastWith());
    }

    public function test_broadcast_event_name_is_notification_created(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'candidature_accepted',
            'data' => ['message' => 'Test'],
        ]);

        $event = new NotificationCreated($notification);

        $this->assertEquals('notification.created', $event->broadcastAs());
    }

    public function test_notification_not_persisted_when_transaction_rolls_back(): void
    {
        Event::fake([JobProcessing::class]);

        try {
            DB::transaction(function () {
                Notification::create([
                    'user_id' => $this->user->id,
                    'type' => 'candidature_accepted',
                    'data' => ['message' => 'Should not persist'],
                ]);

                Event::assertNotDispatched(JobProcessing::class);

                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        Event::assertNotDispatched(JobProcessing::class);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_event_is_dispatched_only_after_transaction_commit(): void
    {
        Event::fake([JobProcessing::class]);

        DB::transaction(function () {
            Notification::create([
                'user_id' => $this->user->id,
                'type' => 'candidature_accepted',
                'data' => ['message' => 'Should broadcast after commit'],
            ]);

            Event::assertNotDispatched(JobProcessing::class);
        });

        Event::assertDispatched(JobProcessing::class, function (JobProcessing $event) {
            $queuedJob = unserialize($event->job->payload()['data']['command']);

            return $queuedJob instanceof BroadcastEvent
                && $queuedJob->event instanceof NotificationCreated
                && $queuedJob->event->userId === $this->user->id
                && $queuedJob->afterCommit === true;
        });
    }

    public function test_event_has_after_commit_enabled(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => 'candidature_accepted',
            'data' => ['message' => 'Test'],
        ]);

        $event = new NotificationCreated($notification);

        $this->assertTrue($event->afterCommit);
    }
}
