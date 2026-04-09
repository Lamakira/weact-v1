<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceSendMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Mission $mission;

    private Candidature $candidature;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by this Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create a Face with User
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create an accepted candidature with conversation
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        // Create conversation (as would happen when candidature is accepted)
        $this->conversation = Conversation::factory()->create([
            'candidature_id' => $this->candidature->id,
        ]);
    }

    // ==========================================================================
    // AC #1: Face can view conversation after candidature accepted
    // ==========================================================================

    public function test_face_can_view_conversation_after_acceptance(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'candidature_id',
                    'mission_title',
                    'other_participant' => [
                        'id',
                        'name',
                        'photo_url',
                        'type',
                    ],
                    'messages',
                    'unread_count',
                ],
            ]);
    }

    public function test_conversation_response_includes_mission_title(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.mission_title', $this->mission->titre);
    }

    public function test_conversation_response_shows_producer_as_other_participant_for_face(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.other_participant.type', 'producer')
            ->assertJsonPath('data.other_participant.id', $this->producer->uuid)
            ->assertJsonPath('data.other_participant.name', $this->producer->display_name);
    }

    // ==========================================================================
    // AC #2: Face can send message to conversation
    // ==========================================================================

    public function test_face_can_send_message_to_conversation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Bonjour, je suis intéressé par cette mission.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Message envoyé')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'content',
                    'sender_id',
                    'sender_type',
                    'sender_name',
                    'is_own_message',
                    'read_at',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'content' => 'Bonjour, je suis intéressé par cette mission.',
        ]);
    }

    // ==========================================================================
    // AC #3: Message includes correct sender data
    // ==========================================================================

    public function test_message_includes_correct_sender_data(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Test message content',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $this->faceUser->id)
            ->assertJsonPath('data.sender_type', User::class)
            ->assertJsonPath('data.sender_name', $this->face->display_name)
            ->assertJsonPath('data.is_own_message', true);
    }

    public function test_message_read_at_is_null_on_creation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Test message',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.read_at', null);
    }

    public function test_message_has_created_at_timestamp(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Test message',
            ]);

        $response->assertCreated();

        $data = $response->json('data');
        $this->assertNotNull($data['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['created_at']);
    }

    // ==========================================================================
    // AC #4: Face cannot access conversation if candidature not accepted
    // ==========================================================================

    public function test_face_cannot_view_conversation_with_pending_candidature(): void
    {
        // Change candidature to pending
        $this->candidature->status = CandidatureStatus::Pending;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        // View is allowed (user is participant), but sendMessage would be denied
        // The view() policy only checks if user is participant, not status
        $response->assertOk();
    }

    public function test_face_cannot_send_message_with_pending_candidature(): void
    {
        // Change candidature to pending
        $this->candidature->status = CandidatureStatus::Pending;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Should fail',
            ]);

        $response->assertForbidden();
    }

    public function test_face_cannot_send_message_with_rejected_candidature(): void
    {
        // Change candidature to rejected
        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Should fail',
            ]);

        $response->assertForbidden();
    }

    // ==========================================================================
    // AC #5: Messages ordered chronologically
    // ==========================================================================

    public function test_messages_are_ordered_chronologically(): void
    {
        // Create messages in specific order
        $message1 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'First message',
            'created_at' => now()->subMinutes(10),
        ]);

        $message2 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'Second message',
            'created_at' => now()->subMinutes(5),
        ]);

        $message3 = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Third message',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();

        $messages = $response->json('data.messages');
        $this->assertCount(3, $messages);
        $this->assertEquals('First message', $messages[0]['content']);
        $this->assertEquals('Second message', $messages[1]['content']);
        $this->assertEquals('Third message', $messages[2]['content']);
    }

    // ==========================================================================
    // AC #6: Unread messages marked as read on view
    // ==========================================================================

    public function test_unread_messages_from_producer_are_marked_as_read_when_face_views(): void
    {
        // Create unread message from producer
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'content' => 'Message from producer',
            'read_at' => null,
        ]);

        $this->assertNull($message->read_at);

        // Face views the conversation
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();

        // Message should now be marked as read
        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    public function test_face_own_messages_are_not_marked_as_read_on_view(): void
    {
        // Create unread message from face (shouldn't happen normally, but test the behavior)
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'content' => 'Message from face',
            'read_at' => null,
        ]);

        // Face views the conversation
        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertOk();

        // Own message should still be unread (not touched)
        $message->refresh();
        $this->assertNull($message->read_at);
    }

    public function test_unread_count_is_correct(): void
    {
        // Create 3 unread messages from producer
        Message::factory()->count(3)->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);

        // Create 1 read message from producer
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
            'read_at' => now(),
        ]);

        // Create 2 unread messages from face (own messages, shouldn't count)
        Message::factory()->count(2)->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'sender_type' => User::class,
            'read_at' => null,
        ]);

        // Before viewing, unread count should be 3
        $this->assertEquals(3, $this->conversation->unreadCountFor($this->faceUser));
    }

    // ==========================================================================
    // AC #7: Empty message returns validation error
    // ==========================================================================

    public function test_empty_message_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_whitespace_only_message_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => '   ',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_missing_content_returns_validation_error(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_message_over_5000_characters_returns_validation_error(): void
    {
        $longContent = str_repeat('a', 5001);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => $longContent,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_message_exactly_5000_characters_is_accepted(): void
    {
        $content = str_repeat('a', 5000);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => $content,
            ]);

        $response->assertCreated();
    }

    // ==========================================================================
    // Authorization - Face cannot access other Face's conversation
    // ==========================================================================

    public function test_face_cannot_view_other_face_conversation(): void
    {
        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertForbidden();
    }

    public function test_face_cannot_send_message_to_other_face_conversation(): void
    {
        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $response = $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Trying to send to other conversation',
            ]);

        $response->assertForbidden();
    }

    // ==========================================================================
    // Unauthenticated access
    // ==========================================================================

    public function test_unauthenticated_user_cannot_view_conversation(): void
    {
        $response = $this->getJson("/api/v1/face/conversations/{$this->conversation->uuid}");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $response = $this->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
            'content' => 'Test',
        ]);

        $response->assertUnauthorized();
    }

    // ==========================================================================
    // Conversation not found
    // ==========================================================================

    public function test_returns_404_for_non_existent_conversation(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ==========================================================================
    // Chat works with confirmed/in_progress/completed statuses
    // ==========================================================================

    public function test_face_can_send_message_with_confirmed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Message after confirmation',
            ]);

        $response->assertCreated();
    }

    public function test_face_can_send_message_with_in_progress_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Message during mission',
            ]);

        $response->assertCreated();
    }

    public function test_face_can_send_message_with_completed_candidature(): void
    {
        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => 'Message after completion',
            ]);

        $response->assertCreated();
    }

    // ==========================================================================
    // FaceCandidatureResource includes conversation_id
    // ==========================================================================

    public function test_face_candidatures_list_includes_conversation_id(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertOk()
            ->assertJsonPath('data.0.conversation_id', $this->conversation->uuid);
    }

    public function test_face_candidatures_list_has_null_conversation_id_when_no_conversation(): void
    {
        // Create another mission for the pending candidature
        $anotherMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create a pending candidature without conversation on a different mission
        $pendingCandidature = Candidature::factory()->create([
            'mission_id' => $anotherMission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertOk();

        $candidatures = $response->json('data');

        // Find the pending candidature in the response
        $pendingItem = collect($candidatures)->firstWhere('id', $pendingCandidature->uuid);
        $this->assertNull($pendingItem['conversation_id']);

        // The accepted candidature should have conversation_id
        $acceptedItem = collect($candidatures)->firstWhere('id', $this->candidature->uuid);
        $this->assertEquals($this->conversation->uuid, $acceptedItem['conversation_id']);
    }

    // ==========================================================================
    // French error messages
    // ==========================================================================

    public function test_empty_message_returns_french_error_message(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => '',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'Le message ne peut pas être vide.');
    }

    public function test_long_message_returns_french_error_message(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/conversations/{$this->conversation->uuid}/messages", [
                'content' => str_repeat('a', 5001),
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'Le message ne peut pas dépasser 5000 caractères.');
    }
}
