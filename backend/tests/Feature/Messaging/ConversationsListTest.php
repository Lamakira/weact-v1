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
 * Tests for conversations list view (Story 7-7).
 *
 * Verifies that users can see a list of all their conversations
 * with proper ordering, unread counts, and pagination.
 */
class ConversationsListTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

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
    }

    /**
     * Helper to create a conversation with associated candidature and mission.
     */
    private function createConversationForFace(Face $face, ?Producer $producer = null): Conversation
    {
        $producer = $producer ?? Producer::factory()->create();
        $producerUser = User::where('userable_type', Producer::class)
            ->where('userable_id', $producer->id)
            ->first();

        if (!$producerUser) {
            $producerUser = User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);
        }

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);
    }

    /**
     * Helper to create a conversation for a Producer.
     */
    private function createConversationForProducer(Producer $producer, ?Face $face = null): Conversation
    {
        $face = $face ?? Face::factory()->create();
        $faceUser = User::where('userable_type', Face::class)
            ->where('userable_id', $face->id)
            ->first();

        if (!$faceUser) {
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
        }

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);
    }

    // ==========================================================================
    // AC #1: Face can list all their conversations
    // ==========================================================================

    public function test_face_can_list_own_conversations(): void
    {
        // Create conversations for this Face
        $conversation1 = $this->createConversationForFace($this->face, $this->producer);
        $conversation2 = $this->createConversationForFace($this->face);

        // Add messages to ensure ordering
        Message::factory()->create([
            'conversation_id' => $conversation1->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => now()->subHour(),
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation2->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'candidature_id',
                    'mission_title',
                    'other_participant' => ['id', 'name', 'photo_url', 'type'],
                    'latest_message',
                    'unread_count',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    public function test_face_only_sees_own_conversations(): void
    {
        // Create conversation for this Face
        $this->createConversationForFace($this->face, $this->producer);

        // Create conversation for another Face
        $otherFace = Face::factory()->create();
        $this->createConversationForFace($otherFace);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // ==========================================================================
    // AC #1: Producer can list all their conversations
    // ==========================================================================

    public function test_producer_can_list_own_conversations(): void
    {
        // Create conversations for this Producer
        $conversation1 = $this->createConversationForProducer($this->producer, $this->face);
        $conversation2 = $this->createConversationForProducer($this->producer);

        // Add messages
        Message::factory()->create([
            'conversation_id' => $conversation1->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'created_at' => now()->subHour(),
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation2->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'candidature_id',
                    'mission_title',
                    'other_participant' => ['id', 'name', 'photo_url', 'type'],
                    'latest_message',
                    'unread_count',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    public function test_producer_only_sees_own_conversations(): void
    {
        // Create conversation for this Producer
        $this->createConversationForProducer($this->producer, $this->face);

        // Create conversation for another Producer
        $otherProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $this->createConversationForProducer($otherProducer);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // ==========================================================================
    // AC #2: Conversations show proper data (participant, mission, last message)
    // ==========================================================================

    public function test_conversation_list_shows_other_participant_info(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.other_participant.type', 'producer');
        $response->assertJsonPath('data.0.other_participant.name', $this->producer->display_name);
    }

    public function test_conversation_list_shows_mission_title(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);
        $missionTitle = $conversation->candidature->mission->titre;

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.mission_title', $missionTitle);
    }

    public function test_conversation_list_shows_latest_message_preview(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        // Create messages
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'First message',
            'created_at' => now()->subHour(),
        ]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Latest message should appear',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.latest_message.content', 'Latest message should appear');
    }

    public function test_long_message_is_truncated_in_preview(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        $longMessage = str_repeat('x', 100);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => $longMessage,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $content = $response->json('data.0.latest_message.content');
        // Content should be truncated to 50 chars + "..."
        $this->assertLessThanOrEqual(53, strlen($content));
    }

    // ==========================================================================
    // AC #3: Unread count is displayed correctly
    // ==========================================================================

    public function test_face_sees_correct_unread_count(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        // Create 3 unread messages from Producer
        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);

        // Create 1 read message from Producer
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'read_at' => now(),
        ]);

        // Create 2 messages from Face (own messages shouldn't count)
        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.unread_count', 3);
    }

    public function test_producer_sees_correct_unread_count(): void
    {
        $conversation = $this->createConversationForProducer($this->producer, $this->face);

        // Create 2 unread messages from Face
        Message::factory()->count(2)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.unread_count', 2);
    }

    public function test_conversation_with_no_unread_shows_zero(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        // Create read messages only
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.unread_count', 0);
    }

    // ==========================================================================
    // AC #4: Empty state returns empty array
    // ==========================================================================

    public function test_face_with_no_conversations_returns_empty_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJson([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'total' => 0,
            ],
        ]);
    }

    public function test_producer_with_no_conversations_returns_empty_array(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations');

        $response->assertOk();
        $response->assertJson([
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'total' => 0,
            ],
        ]);
    }

    // ==========================================================================
    // Ordering: Most recent message first
    // ==========================================================================

    public function test_conversations_ordered_by_latest_message_timestamp(): void
    {
        // Create three conversations
        $conv1 = $this->createConversationForFace($this->face);
        $conv2 = $this->createConversationForFace($this->face);
        $conv3 = $this->createConversationForFace($this->face);

        // Add messages with different timestamps
        Message::factory()->create([
            'conversation_id' => $conv1->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => now()->subHours(3), // Oldest
        ]);

        Message::factory()->create([
            'conversation_id' => $conv2->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => now()->subHour(), // Middle
        ]);

        Message::factory()->create([
            'conversation_id' => $conv3->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'created_at' => now(), // Most recent
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();

        $data = $response->json('data');

        // Should be ordered: conv3, conv2, conv1 (most recent first)
        $this->assertEquals($conv3->uuid, $data[0]['id']);
        $this->assertEquals($conv2->uuid, $data[1]['id']);
        $this->assertEquals($conv1->uuid, $data[2]['id']);
    }

    // ==========================================================================
    // Pagination
    // ==========================================================================

    public function test_conversations_are_paginated(): void
    {
        // Create 20 conversations (more than default page size of 15)
        for ($i = 0; $i < 20; $i++) {
            $conversation = $this->createConversationForFace($this->face);
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $this->faceUser->id,
                'sender_type' => User::class,
            ]);
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 15);
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonCount(15, 'data');
    }

    public function test_can_request_second_page(): void
    {
        // Create 20 conversations
        for ($i = 0; $i < 20; $i++) {
            $conversation = $this->createConversationForFace($this->face);
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $this->faceUser->id,
                'sender_type' => User::class,
            ]);
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations?page=2');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonCount(5, 'data'); // 20 - 15 = 5 remaining
    }

    // ==========================================================================
    // Authorization: Unauthenticated access
    // ==========================================================================

    public function test_unauthenticated_user_cannot_list_face_conversations(): void
    {
        $response = $this->getJson('/api/v1/face/conversations');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_list_producer_conversations(): void
    {
        $response = $this->getJson('/api/v1/producer/conversations');

        $response->assertUnauthorized();
    }

    // ==========================================================================
    // Authorization: Wrong role access
    // ==========================================================================

    public function test_producer_cannot_access_face_conversations_endpoint(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertForbidden();
    }

    public function test_face_cannot_access_producer_conversations_endpoint(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/producer/conversations');

        $response->assertForbidden();
    }

    // ==========================================================================
    // Edge cases
    // ==========================================================================

    public function test_conversation_without_messages_shows_null_latest_message(): void
    {
        $this->createConversationForFace($this->face, $this->producer);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.latest_message', null);
    }

    public function test_latest_message_includes_is_mine_flag(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        // Create message from Face (own message)
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'My own message',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.latest_message.is_mine', true);
    }

    public function test_latest_message_is_mine_false_for_other_sender(): void
    {
        $conversation = $this->createConversationForFace($this->face, $this->producer);

        // Create message from Producer
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Message from other',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations');

        $response->assertOk();
        $response->assertJsonPath('data.0.latest_message.is_mine', false);
    }
}
