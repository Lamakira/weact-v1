<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for conversation history viewing (Story 7-5).
 *
 * Verifies that conversation history is properly displayed
 * with correct ordering, persistence, and participant data.
 */
class ConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

    private Mission $mission;

    private Candidature $candidature;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create Producer user
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create mission owned by producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
        ]);

        // Create accepted candidature
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        // Create conversation
        $this->conversation = Conversation::factory()->create([
            'candidature_id' => $this->candidature->id,
        ]);
    }

    // ==========================================================================
    // AC #1: Messages displayed in chronological order (oldest first)
    // ==========================================================================

    public function test_face_sees_messages_in_chronological_order(): void
    {
        // Create messages out of order (newer first in DB)
        $message3 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Third message',
            'created_at' => now(),
        ]);

        $message1 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'First message',
            'created_at' => now()->subHours(2),
        ]);

        $message2 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Second message',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();

        $messages = $response->json('data.messages');
        $this->assertCount(3, $messages);

        // Assert chronological order (oldest first)
        $this->assertEquals($message1->id, $messages[0]['id']);
        $this->assertEquals($message2->id, $messages[1]['id']);
        $this->assertEquals($message3->id, $messages[2]['id']);
    }

    public function test_producer_sees_messages_in_chronological_order(): void
    {
        // Create messages out of order
        $message2 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'Reply from Face',
            'created_at' => now(),
        ]);

        $message1 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Initial message',
            'created_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}");

        $response->assertOk();

        $messages = $response->json('data.messages');
        $this->assertCount(2, $messages);

        // Assert chronological order
        $this->assertEquals($message1->id, $messages[0]['id']);
        $this->assertEquals($message2->id, $messages[1]['id']);
    }

    public function test_messages_with_same_timestamp_ordered_by_id(): void
    {
        $timestamp = now();

        // Create messages with same timestamp (edge case)
        $message1 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'First at same time',
            'created_at' => $timestamp,
        ]);

        $message2 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Second at same time',
            'created_at' => $timestamp,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $messages = $response->json('data.messages');

        // Should be ordered by id when timestamps match
        $this->assertEquals($message1->id, $messages[0]['id']);
        $this->assertEquals($message2->id, $messages[1]['id']);
    }

    // ==========================================================================
    // AC #3: Conversation persists across sessions
    // ==========================================================================

    public function test_conversation_history_persists_across_requests(): void
    {
        // Create a message
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'Persistent message',
        ]);

        // First request
        $response1 = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        // Second request (simulating page refresh)
        $response2 = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        // Assert same messages returned
        $this->assertEquals(
            $response1->json('data.messages'),
            $response2->json('data.messages')
        );
    }

    public function test_messages_persist_after_sending(): void
    {
        // Send a message
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Test persistence',
            ])
            ->assertCreated();

        // Fetch conversation
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $messages = $response->json('data.messages');
        $this->assertCount(1, $messages);
        $this->assertEquals('Test persistence', $messages[0]['content']);
    }

    // ==========================================================================
    // AC #4: Message displays sender, content, and timestamp
    // ==========================================================================

    public function test_message_includes_content(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'Test message content',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertJsonPath('data.messages.0.content', 'Test message content');
    }

    public function test_message_includes_timestamp(): void
    {
        $timestamp = now();

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => $timestamp,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertJsonStructure([
            'data' => [
                'messages' => [
                    ['created_at'],
                ],
            ],
        ]);
    }

    public function test_message_includes_sender_info(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}");

        // MessageResource uses sender_name as a direct field
        $response->assertJsonStructure([
            'data' => [
                'messages' => [
                    ['sender_name'],
                ],
            ],
        ]);

        // Verify the sender name is the Face's display name
        $response->assertJsonPath('data.messages.0.sender_name', $this->face->display_name);
    }

    // ==========================================================================
    // AC #5: Face sees Producer as other_participant
    // ==========================================================================

    public function test_face_sees_producer_as_other_participant(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();
        $response->assertJsonPath('data.other_participant.type', 'producer');
        $response->assertJsonPath('data.other_participant.name', $this->producer->display_name);
    }

    public function test_face_sees_producer_photo_in_header(): void
    {
        $this->producer->update(['photo_profil' => 'photos/producer.jpg']);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertJsonStructure([
            'data' => [
                'other_participant' => ['photo_url'],
            ],
        ]);
    }

    // ==========================================================================
    // AC #6: Producer sees Face as other_participant
    // ==========================================================================

    public function test_producer_sees_face_as_other_participant(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}");

        $response->assertOk();
        $response->assertJsonPath('data.other_participant.type', 'face');
        $response->assertJsonPath('data.other_participant.name', $this->face->display_name);
    }

    public function test_producer_sees_face_photo_in_header(): void
    {
        $this->face->update(['photo_profil' => 'photos/face.jpg']);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}");

        $response->assertJsonStructure([
            'data' => [
                'other_participant' => ['photo_url'],
            ],
        ]);
    }

    // ==========================================================================
    // AC #2: Empty conversation shows no messages
    // ==========================================================================

    public function test_empty_conversation_returns_empty_messages_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();
        $response->assertJsonPath('data.messages', []);
    }

    // ==========================================================================
    // Conversation includes mission title
    // ==========================================================================

    public function test_conversation_includes_mission_title(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertJsonPath('data.mission_title', $this->mission->titre);
    }

    // ==========================================================================
    // Authorization Tests (complementing FaceSendMessageTest/ProducerSendMessageTest)
    // ==========================================================================

    public function test_unauthenticated_user_cannot_view_conversation_history(): void
    {
        $response = $this->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertUnauthorized();
    }

    public function test_other_face_cannot_view_conversation_history(): void
    {
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertForbidden();
    }

    public function test_other_producer_cannot_view_conversation_history(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $response = $this->actingAs($otherProducerUser)
            ->getJson("/api/v1/producer/conversations/{$this->conversation->uuid}");

        $response->assertForbidden();
    }
}
