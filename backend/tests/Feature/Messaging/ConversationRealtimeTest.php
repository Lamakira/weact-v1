<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Events\ConversationMessageSent;
use App\Events\ConversationMessagesRead;
use App\Events\ConversationUpdated;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConversationRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);
        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        $this->conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function fakeEvents(): void
    {
        Event::fake([ConversationMessageSent::class, ConversationMessagesRead::class, ConversationUpdated::class]);
    }

    /**
     * @return list<string>
     */
    private function channelNames(object $event): array
    {
        return array_map(fn (PrivateChannel $c) => $c->name, $event->broadcastOn());
    }

    private function messageFrom(User $sender): Message
    {
        return Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
        ]);
    }

    public function test_face_send_broadcasts_message_on_conversation_channel_to_others(): void
    {
        $this->fakeEvents();

        $this->actingAs($this->faceUser)
            ->withHeader('X-Socket-ID', '123.456')
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", ['content' => 'Bonjour'])
            ->assertCreated();

        Event::assertDispatched(ConversationMessageSent::class, function (ConversationMessageSent $e): bool {
            $payload = $e->broadcastWith();

            return $this->channelNames($e) === ['private-conversation.'.$this->conversation->uuid]
                && $e->broadcastAs() === 'message.sent'
                && $e->socket === '123.456'
                && $payload['conversation_id'] === $this->conversation->uuid
                && $payload['content'] === 'Bonjour'
                && $payload['sender_id'] === $this->faceUser->id
                && ! array_key_exists('is_own_message', $payload)
                && array_keys($payload) === ['id', 'conversation_id', 'content', 'sender_id', 'sender_type', 'sender_name', 'read_at', 'created_at'];
        });
    }

    public function test_producer_send_broadcasts_message(): void
    {
        $this->fakeEvents();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/conversations/{$this->conversation->uuid}/messages", ['content' => 'Salut'])
            ->assertCreated();

        Event::assertDispatched(ConversationMessageSent::class, fn (ConversationMessageSent $e) => $e->broadcastWith()['sender_id'] === $this->producerUser->id);
    }

    public function test_send_dispatches_conversation_updated_per_recipient_with_own_unread_count(): void
    {
        $this->fakeEvents();
        $this->messageFrom($this->producerUser);
        $this->messageFrom($this->producerUser);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", ['content' => 'Reponse'])
            ->assertCreated();

        Event::assertDispatchedTimes(ConversationUpdated::class, 2);
        Event::assertDispatched(ConversationUpdated::class, function (ConversationUpdated $e): bool {
            $p = $e->broadcastWith();

            return $this->channelNames($e) === ['private-App.Models.User.'.$this->producerUser->id]
                && $e->broadcastAs() === 'conversation.updated'
                && $p['conversation_id'] === $this->conversation->uuid
                && $p['unread_count'] === 1
                && $p['latest_message']['content'] === 'Reponse'
                && $p['latest_message']['sender_id'] === $this->faceUser->id;
        });
        Event::assertDispatched(ConversationUpdated::class, function (ConversationUpdated $e): bool {
            return $this->channelNames($e) === ['private-App.Models.User.'.$this->faceUser->id]
                && $e->broadcastWith()['unread_count'] === 2;
        });
    }

    public function test_updated_payload_truncates_excerpt(): void
    {
        $this->fakeEvents();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", ['content' => str_repeat('a', 200)])
            ->assertCreated();

        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => mb_strlen($e->broadcastWith()['latest_message']['content']) <= 53);
    }

    public function test_read_endpoint_marks_read_and_broadcasts_once(): void
    {
        $this->fakeEvents();
        $m1 = $this->messageFrom($this->producerUser);
        $m2 = $this->messageFrom($this->producerUser);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 2);

        $this->assertNotNull($m1->fresh()->read_at);
        Event::assertDispatched(ConversationMessagesRead::class, function (ConversationMessagesRead $e) use ($m2): bool {
            $p = $e->broadcastWith();

            return $this->channelNames($e) === ['private-conversation.'.$this->conversation->uuid]
                && $e->broadcastAs() === 'messages.read'
                && $p['conversation_id'] === $this->conversation->uuid
                && $p['reader_role'] === 'face'
                && $p['reader_id'] === $this->faceUser->id
                && $p['last_read_message_id'] === $m2->id
                && is_string($p['read_at']);
        });

        // Idempotent: a second call changes nothing and dispatches nothing more.
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 0);
        Event::assertDispatchedTimes(ConversationMessagesRead::class, 1);
    }

    public function test_producer_show_broadcasts_read(): void
    {
        $this->fakeEvents();
        $this->messageFrom($this->faceUser);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}")
            ->assertOk();

        Event::assertDispatched(ConversationMessagesRead::class, fn (ConversationMessagesRead $e) => $e->broadcastWith()['reader_role'] === 'producer');
    }

    public function test_read_endpoint_with_nothing_unread_dispatches_nothing(): void
    {
        $this->fakeEvents();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/conversations/{$this->conversation->uuid}/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 0);

        Event::assertNotDispatched(ConversationMessagesRead::class);
        Event::assertNotDispatched(ConversationUpdated::class);
    }

    public function test_read_endpoint_is_participant_only(): void
    {
        $this->fakeEvents();
        $otherFace = User::factory()->create(['userable_type' => Face::class, 'userable_id' => Face::factory()->create()->id]);
        $otherProducer = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => Producer::factory()->create()->id]);

        $this->actingAs($otherFace)->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/read")->assertForbidden();
        $this->actingAs($otherProducer)->postJson("/api/v1/producer/conversations/{$this->conversation->uuid}/read")->assertForbidden();
        Event::assertNotDispatched(ConversationMessagesRead::class);
    }

    public function test_send_query_count_does_not_grow_with_history(): void
    {
        $this->fakeEvents();
        $this->actingAs($this->faceUser);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", ['content' => 'x'])->assertCreated();

            return count(DB::getQueryLog());
        };

        $first = $count();
        foreach (range(1, 8) as $i) {
            $this->messageFrom($this->producerUser);
        }
        $this->assertSame($first, $count());
    }

    // ---- channel authorization -------------------------------------------

    private function useRealBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    private function authChannel(string $uuid, User|Admin $principal)
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-conversation.'.$uuid,
        ], ['Authorization' => 'Bearer '.$principal->createToken('t', ['2fa'])->plainTextToken]);
    }

    public function test_channel_auth_accepts_both_participants(): void
    {
        $this->useRealBroadcaster();

        $this->authChannel($this->conversation->uuid, $this->faceUser)->assertOk();
        $this->authChannel($this->conversation->uuid, $this->producerUser)->assertOk();
    }

    public function test_channel_auth_refuses_outsiders_admins_and_unknown_uuid(): void
    {
        $otherFace = User::factory()->create(['userable_type' => Face::class, 'userable_id' => Face::factory()->create()->id]);
        $otherProducer = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => Producer::factory()->create()->id]);
        $admin = Admin::factory()->create(['id' => $this->faceUser->id]);
        $this->useRealBroadcaster();

        $this->authChannel($this->conversation->uuid, $otherFace)->assertStatus(403);
        $this->authChannel($this->conversation->uuid, $otherProducer)->assertStatus(403);
        $this->authChannel($this->conversation->uuid, $admin)->assertStatus(403);
        $this->authChannel('00000000-0000-0000-0000-000000000000', $this->faceUser)->assertStatus(403);
        $this->authChannel((string) $this->conversation->id, $this->faceUser)->assertStatus(403);
    }
}
