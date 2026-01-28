<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagingSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ==========================================================================
    // AC #1: Conversations table has all required fields
    // ==========================================================================

    public function test_conversations_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('conversations'));

        $expectedColumns = [
            'id',
            'candidature_id',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('conversations', $column),
                "Column '{$column}' should exist in conversations table"
            );
        }
    }

    // ==========================================================================
    // AC #2: Foreign key to candidatures with cascade delete
    // ==========================================================================

    public function test_conversation_has_foreign_key_to_candidature(): void
    {
        $candidature = Candidature::factory()->accepted()->create();

        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->assertEquals($candidature->id, $conversation->candidature_id);
        $this->assertInstanceOf(Candidature::class, $conversation->candidature);
    }

    public function test_foreign_key_constraint_deletes_conversation_when_candidature_deleted(): void
    {
        $candidature = Candidature::factory()->accepted()->create();
        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $conversationId = $conversation->id;

        $candidature->delete();

        $this->assertDatabaseMissing('conversations', ['id' => $conversationId]);
    }

    // ==========================================================================
    // AC #3: Messages table has all required fields
    // ==========================================================================

    public function test_messages_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('messages'));

        $expectedColumns = [
            'id',
            'conversation_id',
            'sender_id',
            'sender_type',
            'content',
            'read_at',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('messages', $column),
                "Column '{$column}' should exist in messages table"
            );
        }
    }

    // ==========================================================================
    // AC #4: Foreign key to conversations with cascade delete
    // ==========================================================================

    public function test_message_has_foreign_key_to_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertEquals($conversation->id, $message->conversation_id);
        $this->assertInstanceOf(Conversation::class, $message->conversation);
    }

    public function test_foreign_key_constraint_deletes_messages_when_conversation_deleted(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $messageId = $message->id;

        $conversation->delete();

        $this->assertDatabaseMissing('messages', ['id' => $messageId]);
    }

    // ==========================================================================
    // AC #5: Polymorphic sender relationship
    // ==========================================================================

    public function test_message_has_polymorphic_sender_relationship(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertInstanceOf(User::class, $message->sender);
        $this->assertEquals($user->id, $message->sender->id);
    }

    public function test_user_can_have_many_sent_messages(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();

        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertCount(3, $user->sentMessages);
    }

    // ==========================================================================
    // AC #6: Unique constraint on candidature_id
    // ==========================================================================

    public function test_unique_constraint_prevents_duplicate_conversations_for_same_candidature(): void
    {
        $candidature = Candidature::factory()->accepted()->create();

        Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->expectException(QueryException::class);

        Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);
    }

    // ==========================================================================
    // AC #7: Conversation model relationships
    // ==========================================================================

    public function test_conversation_belongs_to_candidature(): void
    {
        $candidature = Candidature::factory()->accepted()->create();
        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->assertInstanceOf(Candidature::class, $conversation->candidature);
        $this->assertEquals($candidature->id, $conversation->candidature->id);
    }

    public function test_conversation_has_many_messages(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertCount(5, $conversation->messages);
    }

    public function test_conversation_can_get_face_through_candidature(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create();

        $candidature = Candidature::factory()->accepted()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->assertInstanceOf(Face::class, $conversation->face);
        $this->assertEquals($face->id, $conversation->face->id);
    }

    public function test_conversation_can_get_producer_through_candidature_mission(): void
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->accepted()->create([
            'mission_id' => $mission->id,
        ]);

        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->assertInstanceOf(Producer::class, $conversation->producer);
        $this->assertEquals($producer->id, $conversation->producer->id);
    }

    public function test_conversation_can_get_latest_message(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        // Create messages with different timestamps
        $oldMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
            'content' => 'Old message',
            'created_at' => now()->subMinutes(10),
        ]);

        $latestMessage = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
            'content' => 'Latest message',
            'created_at' => now(),
        ]);

        $this->assertEquals($latestMessage->id, $conversation->latestMessage->id);
        $this->assertEquals('Latest message', $conversation->latestMessage->content);
    }

    public function test_conversation_can_count_unread_messages_for_user(): void
    {
        $conversation = Conversation::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Messages from user1 (should be counted as unread for user2)
        Message::factory()->count(3)->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'sender_type' => User::class,
        ]);

        // Messages from user2 (should NOT be counted as unread for user2)
        Message::factory()->count(2)->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user2->id,
            'sender_type' => User::class,
        ]);

        // Read message from user1 (should NOT be counted)
        Message::factory()->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user1->id,
            'sender_type' => User::class,
        ]);

        $this->assertEquals(3, $conversation->unreadCountFor($user2));
    }

    // ==========================================================================
    // AC #8: Message model relationships
    // ==========================================================================

    public function test_message_belongs_to_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
        ]);

        $this->assertInstanceOf(Conversation::class, $message->conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
    }

    public function test_message_mark_as_read_method(): void
    {
        $message = Message::factory()->unread()->create();

        $this->assertNull($message->read_at);
        $this->assertFalse($message->isRead());

        $result = $message->markAsRead();

        $this->assertTrue($result);
        $message->refresh();
        $this->assertNotNull($message->read_at);
        $this->assertTrue($message->isRead());
    }

    public function test_message_mark_as_read_returns_false_if_already_read(): void
    {
        $message = Message::factory()->read()->create();

        $this->assertTrue($message->isRead());

        $result = $message->markAsRead();

        $this->assertFalse($result);
    }

    public function test_message_unread_scope(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        Message::factory()->count(3)->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        Message::factory()->count(2)->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertCount(3, Message::unread()->get());
    }

    public function test_message_read_scope(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        Message::factory()->count(3)->unread()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        Message::factory()->count(2)->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $this->assertCount(2, Message::read()->get());
    }

    // ==========================================================================
    // AC #9: Efficient querying through candidature relationship
    // ==========================================================================

    public function test_candidature_has_one_conversation_relationship(): void
    {
        $candidature = Candidature::factory()->accepted()->create();
        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);

        $this->assertInstanceOf(Conversation::class, $candidature->conversation);
        $this->assertEquals($conversation->id, $candidature->conversation->id);
    }

    public function test_candidature_has_conversation_helper_method(): void
    {
        $candidatureWithConversation = Candidature::factory()->accepted()->create();
        $candidatureWithoutConversation = Candidature::factory()->accepted()->create();

        Conversation::factory()->create([
            'candidature_id' => $candidatureWithConversation->id,
        ]);

        $this->assertTrue($candidatureWithConversation->hasConversation());
        $this->assertFalse($candidatureWithoutConversation->hasConversation());
    }

    // ==========================================================================
    // Cascade delete: candidature → conversation → messages
    // ==========================================================================

    public function test_cascade_delete_from_candidature_to_conversation_to_messages(): void
    {
        $candidature = Candidature::factory()->accepted()->create();
        $conversation = Conversation::factory()->create([
            'candidature_id' => $candidature->id,
        ]);
        $user = User::factory()->create();

        $messages = Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        $conversationId = $conversation->id;
        $messageIds = $messages->pluck('id')->toArray();

        // Delete the candidature
        $candidature->delete();

        // Verify conversation is deleted
        $this->assertDatabaseMissing('conversations', ['id' => $conversationId]);

        // Verify all messages are deleted
        foreach ($messageIds as $messageId) {
            $this->assertDatabaseMissing('messages', ['id' => $messageId]);
        }
    }

    // ==========================================================================
    // Factory tests
    // ==========================================================================

    public function test_conversation_factory_creates_accepted_candidature_by_default(): void
    {
        $conversation = Conversation::factory()->create();

        $this->assertEquals(
            'accepted',
            $conversation->candidature->status->value
        );
    }

    public function test_message_factory_states_work_correctly(): void
    {
        $unreadMessage = Message::factory()->unread()->create();
        $readMessage = Message::factory()->read()->create();

        $this->assertNull($unreadMessage->read_at);
        $this->assertNotNull($readMessage->read_at);
    }

    // ==========================================================================
    // Eager loading tests
    // ==========================================================================

    public function test_conversation_eager_loading_with_candidature_and_messages(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        Message::factory()->count(3)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        // Test eager loading
        $loadedConversation = Conversation::with(['candidature', 'messages'])
            ->find($conversation->id);

        $this->assertTrue($loadedConversation->relationLoaded('candidature'));
        $this->assertTrue($loadedConversation->relationLoaded('messages'));
        $this->assertCount(3, $loadedConversation->messages);
    }

    public function test_message_eager_loading_with_conversation_and_sender(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => User::class,
        ]);

        // Test eager loading
        $loadedMessage = Message::with(['conversation', 'sender'])
            ->find($message->id);

        $this->assertTrue($loadedMessage->relationLoaded('conversation'));
        $this->assertTrue($loadedMessage->relationLoaded('sender'));
    }
}
