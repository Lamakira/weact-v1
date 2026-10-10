<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Events\ConversationUpdated;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConversationUnreadCountTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function conversation(?Face $face = null, ?Producer $producer = null): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => ($producer ?? $this->producer)->id,
            'status' => MissionStatus::Published,
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => ($face ?? $this->face)->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function message(Conversation $conversation, User $sender, bool $read = false): Message
    {
        return Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_counts_conversations_not_messages(): void
    {
        $a = $this->conversation();
        $b = $this->conversation();
        $this->message($a, $this->producerUser);
        $this->message($a, $this->producerUser);
        $this->message($a, $this->producerUser);
        $this->message($b, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['count' => 2]]);
    }

    public function test_own_and_read_messages_are_excluded(): void
    {
        $own = $this->conversation();
        $read = $this->conversation();
        $this->message($own, $this->faceUser);
        $this->message($read, $this->producerUser, true);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_other_users_conversations_are_excluded(): void
    {
        $otherFace = Face::factory()->create();
        $otherProducer = Producer::factory()->create();
        $foreign = $this->conversation($otherFace, $otherProducer);
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $this->message($foreign, $otherProducerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertJsonPath('data.count', 0);
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations/unread-count')
            ->assertJsonPath('data.count', 0);
    }

    public function test_producer_route_counts_for_producer(): void
    {
        $a = $this->conversation();
        $this->conversation();
        $this->message($a, $this->faceUser);
        $this->message($a, $this->faceUser);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['count' => 1]]);
    }

    public function test_query_count_is_constant(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations/unread-count')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->message($this->conversation(), $this->producerUser);
        $few = $measure();

        for ($i = 0; $i < 5; $i++) {
            $c = $this->conversation();
            $this->message($c, $this->producerUser);
            $this->message($c, $this->producerUser);
        }

        $this->assertSame($few, $measure());
    }

    public function test_unverified_email_user_gets_same_count_as_the_list(): void
    {
        $this->faceUser->forceFill(['email_verified_at' => null])->save();
        $this->message($this->conversation(), $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_wrong_role_and_admin_are_refused(): void
    {
        $this->actingAs($this->producerUser)->getJson('/api/v1/face/conversations/unread-count')->assertForbidden();
        $this->actingAs($this->faceUser)->getJson('/api/v1/producer/conversations/unread-count')->assertForbidden();

        $admin = Admin::factory()->create();
        $adminUser = User::factory()->create([
            'userable_type' => Admin::class,
            'userable_id' => $admin->id,
        ]);
        $this->actingAs($adminUser)->getJson('/api/v1/face/conversations/unread-count')->assertForbidden();
        $this->actingAs($adminUser)->getJson('/api/v1/producer/conversations/unread-count')->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/face/conversations/unread-count')->assertUnauthorized();
        $this->getJson('/api/v1/producer/conversations/unread-count')->assertUnauthorized();
    }

    public function test_conversation_updated_carries_unread_conversations_count_per_recipient(): void
    {
        Event::fake([ConversationUpdated::class]);

        // Producer has 2 other conversations unread from the Face; Face has 1 unread from the Producer
        $other1 = $this->conversation();
        $other2 = $this->conversation();
        $this->message($other1, $this->faceUser);
        $this->message($other2, $this->faceUser);
        $other3 = $this->conversation();
        $this->message($other3, $this->producerUser);

        $current = $this->conversation();
        $this->message($current, $this->producerUser);

        // Face replies in $current: Face's own count stays 2 (other3 + current), Producer: 3 (other1, other2, current)
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$current->uuid}/messages", ['content' => 'Salut'])
            ->assertCreated();

        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->producerUser->id
            && $e->broadcastWith()['unread_conversations_count'] === 3);
        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->faceUser->id
            && $e->broadcastWith()['unread_conversations_count'] === 2);
    }

    public function test_mark_read_update_carries_reader_new_conversations_count(): void
    {
        Event::fake([ConversationUpdated::class]);

        $a = $this->conversation();
        $b = $this->conversation();
        $this->message($a, $this->producerUser);
        $this->message($b, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$a->uuid}/read")
            ->assertOk();

        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->faceUser->id
            && $e->broadcastWith()['unread_conversations_count'] === 1);
    }

    public function test_list_show_and_read_responses_carry_the_global_count(): void
    {
        $a = $this->conversation();
        $b = $this->conversation();
        $this->message($a, $this->producerUser);
        $this->message($b, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations')
            ->assertOk()
            ->assertJsonPath('meta.unread_conversations_count', 2);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$a->uuid}")
            ->assertOk()
            ->assertJsonPath('meta.unread_conversations_count', 1);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$b->uuid}/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 1)
            ->assertJsonPath('data.unread_conversations_count', 0);
    }
}
