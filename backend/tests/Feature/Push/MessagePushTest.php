<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Jobs\SendWebPush;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessagePushTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webpush.vapid.public_key' => 'public-key-test',
            'webpush.vapid.private_key' => 'private-key-test',
        ]);
        Cache::flush();

        $producer = Producer::factory()->create(['first_name' => 'Awa']);
        $this->producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $mission = Mission::factory()->create(['producer_id' => $producer->id, 'status' => MissionStatus::Published]);
        $face = Face::factory()->create(['prenom' => 'Koffi']);
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        $this->conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function faceSends(string $content = 'Bonjour'): void
    {
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", ['content' => $content])
            ->assertCreated();
    }

    private function producerSends(string $content = 'Salut'): void
    {
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/conversations/{$this->conversation->uuid}/messages", ['content' => $content])
            ->assertCreated();
    }

    public function test_new_message_pushes_the_recipient_with_sender_first_name_and_conversation_url(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');

        $this->faceSends('Bonjour, je suis disponible demain');

        Bus::assertDispatched(SendWebPush::class, function (SendWebPush $job): bool {
            return $job->userId === $this->producerUser->id
                && $job->payload['title'] === 'Nouveau message'
                && $job->payload['body'] === 'Koffi: Bonjour, je suis disponible demain'
                && $job->payload['url'] === "/producer/conversations/{$this->conversation->uuid}"
                && $job->payload['tag'] === "conversation:{$this->conversation->uuid}";
        });
        Bus::assertDispatchedTimes(SendWebPush::class, 1);
    }

    public function test_producer_message_pushes_the_face_with_the_face_url(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->faceUser->updatePushSubscription('https://push.example/f', 'k', 'a');

        $this->producerSends();

        Bus::assertDispatched(SendWebPush::class, fn (SendWebPush $job): bool => $job->userId === $this->faceUser->id
            && $job->payload['body'] === 'Awa: Salut'
            && $job->payload['url'] === "/face/conversations/{$this->conversation->uuid}");
    }

    public function test_at_most_one_push_per_conversation_and_recipient_within_five_minutes(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');

        $this->faceSends('un');
        $this->faceSends('deux');
        $this->faceSends('trois');

        Bus::assertDispatchedTimes(SendWebPush::class, 1);

        $this->travel(301)->seconds();

        $this->faceSends('quatre');

        Bus::assertDispatchedTimes(SendWebPush::class, 2);
    }

    public function test_throttle_is_per_recipient(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');
        $this->faceUser->updatePushSubscription('https://push.example/f', 'k', 'a');

        $this->faceSends();
        $this->producerSends();

        Bus::assertDispatchedTimes(SendWebPush::class, 2);
    }

    public function test_no_push_without_subscription_and_the_throttle_is_not_consumed(): void
    {
        Bus::fake([SendWebPush::class]);

        $this->faceSends('sans abonnement');
        Bus::assertNotDispatched(SendWebPush::class);

        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');
        $this->faceSends('avec abonnement');
        Bus::assertDispatchedTimes(SendWebPush::class, 1);
    }

    public function test_no_push_when_vapid_keys_are_missing(): void
    {
        config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');

        $this->faceSends();

        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_the_sender_is_never_pushed_about_their_own_message(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->faceUser->updatePushSubscription('https://push.example/f', 'k', 'a');

        $this->faceSends();

        Bus::assertNotDispatched(SendWebPush::class);
    }

    public function test_sending_still_succeeds_when_the_push_layer_throws(): void
    {
        Event::fake();
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('queue down'));

        $this->faceSends('message malgré tout');

        $this->assertDatabaseHas('messages', ['content' => 'message malgré tout']);
    }
}
