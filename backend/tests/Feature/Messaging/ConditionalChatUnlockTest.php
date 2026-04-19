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

    /**
     * Promote the candidature to Accepted and provision its conversation.
     *
     * Stands in for the state transition that historically happened inside the
     * legacy manual-accept endpoint (removed by FIX-20.3). The policy and
     * conversation-lifecycle assertions in this suite only care that the
     * candidature reaches Accepted and that its Conversation exists — they are
     * independent of whichever workflow (FedaPay paid selection or future)
     * performs the promotion in production code.
     */
    private function acceptCandidatureDirectly(): Conversation
    {
        $this->candidature->status = CandidatureStatus::Accepted;
        $this->candidature->save();

        return Conversation::firstOrCreate(['candidature_id' => $this->candidature->id]);
    }

    // ==========================================================================
    // AC #2: Idempotent conversation provisioning
    // ==========================================================================

    public function test_no_duplicate_conversation_when_acceptance_is_retried(): void
    {
        // First acceptance provisions the conversation
        $this->acceptCandidatureDirectly();

        // A second firstOrCreate call must return the same row
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
        $conversation = $this->acceptCandidatureDirectly();

        $policy = new ConversationPolicy;
        $this->assertTrue($policy->view($this->faceUser, $conversation));
    }

    public function test_producer_can_access_conversation_after_acceptance(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

        $policy = new ConversationPolicy;
        $this->assertTrue($policy->view($this->producerUser, $conversation));
    }

    public function test_other_face_cannot_access_conversation(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

        // Create another Face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $policy = new ConversationPolicy;
        $this->assertFalse($policy->view($otherFaceUser, $conversation));
    }

    public function test_other_producer_cannot_access_conversation(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

        // Create another Producer
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $policy = new ConversationPolicy;
        $this->assertFalse($policy->view($otherProducerUser, $conversation));
    }

    // ==========================================================================
    // AC #4: 403 when candidature is pending or rejected
    // ==========================================================================

    public function test_send_message_denied_when_candidature_is_pending(): void
    {
        // Create conversation manually for a pending candidature (shouldn't happen but test the guard)
        $conversation = Conversation::create(['candidature_id' => $this->candidature->id]);

        $policy = new ConversationPolicy;
        $this->assertFalse($policy->sendMessage($this->faceUser, $conversation));
        $this->assertFalse($policy->sendMessage($this->producerUser, $conversation));
    }

    public function test_send_message_denied_when_candidature_is_rejected(): void
    {
        // Accept first (to provision the conversation), then flip to rejected
        $conversation = $this->acceptCandidatureDirectly();

        $this->candidature->status = CandidatureStatus::Rejected;
        $this->candidature->save();

        $policy = new ConversationPolicy;
        $this->assertFalse($policy->sendMessage($this->faceUser, $conversation));
        $this->assertFalse($policy->sendMessage($this->producerUser, $conversation));
    }

    // ==========================================================================
    // AC #5: Conversation persists when status changes (no deletion)
    // ==========================================================================

    public function test_conversation_persists_when_status_changes_to_confirmed(): void
    {
        $conversationId = $this->acceptCandidatureDirectly()->id;

        // Manually transition to confirmed (the Face::confirm endpoint requires a Paid
        // MissionPayment which this suite does not set up)
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    public function test_conversation_persists_when_status_changes_to_in_progress(): void
    {
        $conversationId = $this->acceptCandidatureDirectly()->id;

        $this->candidature->status = CandidatureStatus::InProgress;
        $this->candidature->save();

        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    public function test_conversation_persists_when_status_changes_to_completed(): void
    {
        $conversationId = $this->acceptCandidatureDirectly()->id;

        $this->candidature->status = CandidatureStatus::Completed;
        $this->candidature->save();

        $this->assertDatabaseHas('conversations', ['id' => $conversationId]);
    }

    // ==========================================================================
    // AC #6: Conversation accessible after Face confirms
    // ==========================================================================

    public function test_conversation_accessible_after_face_confirms(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

        // Simulate the Face confirming their participation (real endpoint requires
        // a Paid MissionPayment which this policy-focused suite does not set up)
        $this->candidature->status = CandidatureStatus::Confirmed;
        $this->candidature->save();

        // Verify both can still access after confirm
        $policy = new ConversationPolicy;
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
        $conversation = $this->acceptCandidatureDirectly();

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
        $this->acceptCandidatureDirectly();

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
    // Policy auto-discovery verification (using Gate facade)
    // ==========================================================================

    public function test_policy_is_auto_discovered_by_laravel(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

        // Verify policy works through Gate facade (auto-discovery)
        $this->assertTrue($this->faceUser->can('view', $conversation));
        $this->assertTrue($this->producerUser->can('view', $conversation));
        $this->assertTrue($this->faceUser->can('sendMessage', $conversation));
        $this->assertTrue($this->producerUser->can('sendMessage', $conversation));
    }

    public function test_policy_denies_other_users_via_gate(): void
    {
        $conversation = $this->acceptCandidatureDirectly();

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
