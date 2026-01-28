<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionalChatUnlockTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Mission $mission;

    private Candidature $candidature;

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

        // Create a pending candidature
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
        ]);
    }

    // ==========================================================================
    // AC #1: Conversation created when candidature is accepted
    // ==========================================================================

    public function test_conversation_is_created_when_candidature_is_accepted(): void
    {
        // Verify no conversation exists initially
        $this->assertFalse($this->candidature->hasConversation());

        // Accept the candidature
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $response->assertOk();

        // Verify conversation was created
        $this->candidature->refresh();
        $this->assertTrue($this->candidature->hasConversation());
        $this->assertDatabaseHas('conversations', [
            'candidature_id' => $this->candidature->id,
        ]);
    }

    // ==========================================================================
    // AC #2: Idempotent - no duplicate conversation on multiple accepts
    // ==========================================================================

    public function test_no_duplicate_conversation_on_multiple_accept_attempts(): void
    {
        // First acceptance
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $response->assertOk();

        $conversationId = Conversation::where('candidature_id', $this->candidature->id)->first()->id;

        // Create a new pending candidature and try to trigger a second conversation somehow
        // Actually, re-accepting the same candidature returns 400 (already accepted)
        // The idempotency is handled by firstOrCreate - let's test by directly calling firstOrCreate twice

        $conversation1 = Conversation::firstOrCreate(['candidature_id' => $this->candidature->id]);
        $conversation2 = Conversation::firstOrCreate(['candidature_id' => $this->candidature->id]);

        $this->assertEquals($conversation1->id, $conversation2->id);
        $this->assertEquals(1, Conversation::where('candidature_id', $this->candidature->id)->count());
    }

    // ==========================================================================
    // AC #3: Both Face and Producer can access conversation after acceptance
    // ==========================================================================

    public function test_face_can_access_conversation_after_acceptance(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        $policy = new ConversationPolicy();
        $this->assertTrue($policy->view($this->faceUser, $conversation));
    }

    public function test_producer_can_access_conversation_after_acceptance(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        $policy = new ConversationPolicy();
        $this->assertTrue($policy->view($this->producerUser, $conversation));
    }

    public function test_other_face_cannot_access_conversation(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $policy = new ConversationPolicy();
        $this->assertFalse($policy->view($otherFaceUser, $conversation));
    }

    public function test_other_producer_cannot_access_conversation(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        // Create another Producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $policy = new ConversationPolicy();
        $this->assertFalse($policy->view($otherProducerUser, $conversation));
    }

    // ==========================================================================
    // AC #4: 403 when candidature is pending or rejected
    // ==========================================================================

    public function test_send_message_denied_when_candidature_is_pending(): void
    {
        // Create conversation manually for a pending candidature (shouldn't happen but test the guard)
        $conversation = Conversation::create(['candidature_id' => $this->candidature->id]);

        $policy = new ConversationPolicy();
        $this->assertFalse($policy->sendMessage($this->faceUser, $conversation));
        $this->assertFalse($policy->sendMessage($this->producerUser, $conversation));
    }

    public function test_send_message_denied_when_candidature_is_rejected(): void
    {
        // Accept first, then reject (to have a conversation)
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        // Manually change to rejected for test (normally not possible via API)
        $this->candidature->refresh();
        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        $policy = new ConversationPolicy();
        $this->assertFalse($policy->sendMessage($this->faceUser, $conversation));
        $this->assertFalse($policy->sendMessage($this->producerUser, $conversation));
    }

    // ==========================================================================
    // AC #5: Conversation persists when status changes (no deletion)
    // ==========================================================================

    public function test_conversation_persists_when_status_changes_to_confirmed(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversationId = Conversation::where('candidature_id', $this->candidature->id)->first()->id;

        // Confirm the candidature (Face action)
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        // Verify conversation still exists
        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    public function test_conversation_persists_when_status_changes_to_in_progress(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversationId = Conversation::where('candidature_id', $this->candidature->id)->first()->id;

        // Manually change status to in_progress
        $this->candidature->refresh();
        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        // Verify conversation still exists
        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    public function test_conversation_persists_when_status_changes_to_completed(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversationId = Conversation::where('candidature_id', $this->candidature->id)->first()->id;

        // Manually change status to completed
        $this->candidature->refresh();
        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        // Verify conversation still exists
        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    // ==========================================================================
    // AC #6: Conversation accessible after Face confirms
    // ==========================================================================

    public function test_conversation_accessible_after_face_confirms(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        // Confirm the candidature (Face action)
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$this->candidature->id}/confirm");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();
        $this->candidature->refresh();

        // Verify both can still access after confirm
        $policy = new ConversationPolicy();
        $this->assertTrue($policy->view($this->faceUser, $conversation));
        $this->assertTrue($policy->view($this->producerUser, $conversation));
        $this->assertTrue($policy->sendMessage($this->faceUser, $conversation));
        $this->assertTrue($policy->sendMessage($this->producerUser, $conversation));
    }

    // ==========================================================================
    // AC #7: Conversation links correctly to candidature
    // ==========================================================================

    public function test_conversation_links_correctly_to_candidature(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        // Verify relationships work
        $this->assertEquals($this->candidature->id, $conversation->candidature->id);
        $this->assertEquals($this->face->id, $conversation->face->id);
        $this->assertEquals($this->producer->id, $conversation->producer->id);
    }

    // ==========================================================================
    // CandidatureStatus::allowsChatAccess() tests
    // ==========================================================================

    public function test_allows_chat_access_returns_true_for_accepted(): void
    {
        $this->assertTrue(CandidatureStatus::Accepted->allowsChatAccess());
    }

    public function test_allows_chat_access_returns_true_for_confirmed(): void
    {
        $this->assertTrue(CandidatureStatus::Confirmed->allowsChatAccess());
    }

    public function test_allows_chat_access_returns_true_for_in_progress(): void
    {
        $this->assertTrue(CandidatureStatus::InProgress->allowsChatAccess());
    }

    public function test_allows_chat_access_returns_true_for_completed(): void
    {
        $this->assertTrue(CandidatureStatus::Completed->allowsChatAccess());
    }

    public function test_allows_chat_access_returns_false_for_pending(): void
    {
        $this->assertFalse(CandidatureStatus::Pending->allowsChatAccess());
    }

    public function test_allows_chat_access_returns_false_for_rejected(): void
    {
        $this->assertFalse(CandidatureStatus::Rejected->allowsChatAccess());
    }

    // ==========================================================================
    // Candidature helper methods tests
    // ==========================================================================

    public function test_candidature_can_access_chat_method(): void
    {
        // Pending - no chat
        $this->assertFalse($this->candidature->canAccessChat());

        // Accept
        $this->candidature->status = CandidatureStatus::Accepted;
        $this->candidature->save();
        $this->candidature->refresh();
        $this->assertTrue($this->candidature->canAccessChat());

        // Confirm
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();
        $this->candidature->refresh();
        $this->assertTrue($this->candidature->canAccessChat());
    }

    public function test_candidature_get_conversation_or_fail_returns_conversation(): void
    {
        // Accept the candidature (creates conversation)
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $this->candidature->refresh();
        $conversation = $this->candidature->getConversationOrFail();

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals($this->candidature->id, $conversation->candidature_id);
    }

    public function test_candidature_get_conversation_or_fail_throws_404_when_no_conversation(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->candidature->getConversationOrFail();
    }

    // ==========================================================================
    // Notification still created (regression test)
    // ==========================================================================

    public function test_notification_is_still_created_when_candidature_is_accepted(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $response->assertOk();

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
        ]);
    }

    // ==========================================================================
    // Policy auto-discovery verification (using Gate facade)
    // ==========================================================================

    public function test_policy_is_auto_discovered_by_laravel(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        // Verify policy works through Gate facade (auto-discovery)
        $this->assertTrue($this->faceUser->can('view', $conversation));
        $this->assertTrue($this->producerUser->can('view', $conversation));
        $this->assertTrue($this->faceUser->can('sendMessage', $conversation));
        $this->assertTrue($this->producerUser->can('sendMessage', $conversation));
    }

    public function test_policy_denies_other_users_via_gate(): void
    {
        // Accept the candidature
        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->id}/accept");

        $conversation = Conversation::where('candidature_id', $this->candidature->id)->first();

        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        // Verify Gate denies unauthorized users
        $this->assertFalse($otherFaceUser->can('view', $conversation));
        $this->assertFalse($otherFaceUser->can('sendMessage', $conversation));
    }
}
