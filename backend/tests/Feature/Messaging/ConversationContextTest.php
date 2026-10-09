<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\Messaging\ConversationContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contexte (mission / UGC) des conversations : forme par type lié et par rôle,
 * mapping statut -> étapes, absence de fuite de montants entre rôles.
 */
class ConversationContextTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private Producer $producer;

    private User $faceUser;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $missionAttrs
     */
    private function makeConversation(
        CandidatureStatus $status = CandidatureStatus::Accepted,
        array $missionAttrs = [],
        ?Face $face = null,
        ?Producer $producer = null,
    ): Conversation {
        $mission = Mission::factory()->create(array_merge([
            'producer_id' => ($producer ?? $this->producer)->id,
            'titre' => 'Campagne Karité',
            'lieu' => 'Cotonou',
            'budget' => 100000,
        ], $missionAttrs));

        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => ($face ?? $this->face)->id,
            'status' => $status,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function addPaidEntry(Conversation $conversation, int $budgetParFace, int $faceReceives, EscrowStatus $escrow = EscrowStatus::Locked): void
    {
        $candidature = $conversation->candidature;
        $payment = MissionPayment::create([
            'mission_id' => $candidature->mission_id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => $budgetParFace,
            'montant_sous_total' => $budgetParFace,
            'commission_producteur' => (int) round($budgetParFace * 0.10),
            'montant_total_producteur' => (int) round($budgetParFace * 1.10),
            'commission_faces_total' => $budgetParFace - $faceReceives,
            'montant_total_faces' => $faceReceives,
            'status' => MissionPaymentStatus::Paid,
        ]);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => $faceReceives,
            'escrow_status' => $escrow,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(User $user, Conversation $conversation): array
    {
        $prefix = $user->userable_type === Face::class ? 'face' : 'producer';
        $response = $this->actingAs($user)->getJson("/api/v1/{$prefix}/conversations/{$conversation->uuid}");
        $response->assertOk();

        return $response->json('data.context');
    }

    public function test_standard_mission_context_for_face_exposes_only_what_she_receives(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted, [
            'type_mission' => MissionType::Publicite,
            'date_tournage' => '2030-05-12',
        ]);
        $this->addPaidEntry($conversation, 100000, 85000);

        $context = $this->contextFor($this->faceUser, $conversation);

        $this->assertSame('mission', $context['type']);
        $this->assertSame('Mission', $context['type_label']);
        $this->assertSame('Campagne Karité', $context['title']);
        $this->assertSame('Cotonou', $context['lieu']);
        $this->assertSame('2030-05-12', $context['date_tournage']);
        $this->assertSame('accepted', $context['candidature_status']);
        $this->assertSame(['face_receives' => 85000, 'product_value' => null], $context['amounts']);
        $this->assertArrayNotHasKey('service_fee', $context['amounts']);
        $this->assertArrayNotHasKey('total', $context['amounts']);
        $this->assertArrayNotHasKey('cachet', $context['amounts']);
    }

    public function test_standard_mission_context_for_producer_exposes_cachet_fee_and_total_only(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted);
        $this->addPaidEntry($conversation, 100000, 85000);

        $context = $this->contextFor($this->producerUser, $conversation);

        $this->assertSame('mission', $context['type']);
        $this->assertSame(
            ['cachet' => 100000, 'service_fee' => 10000, 'total' => 110000, 'product_value' => null],
            $context['amounts'],
        );
        $this->assertArrayNotHasKey('face_receives', $context['amounts']);
        $this->assertStringNotContainsString('85000', json_encode($context));
    }

    public function test_standard_mission_without_payment_entry_has_null_amounts(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted);

        $face = $this->contextFor($this->faceUser, $conversation);
        $producer = $this->contextFor($this->producerUser, $conversation);

        $this->assertSame(['face_receives' => null, 'product_value' => null], $face['amounts']);
        $this->assertSame(
            ['cachet' => null, 'service_fee' => null, 'total' => null, 'product_value' => null],
            $producer['amounts'],
        );
    }

    public function test_ugc_product_only_context_per_role(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted, [
            'type_mission' => MissionType::Ugc,
            'type_compensation' => CompensationType::Product,
            'nom_produit' => 'Crème',
            'valeur_produit' => 25000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $face = $this->contextFor($this->faceUser, $conversation);
        $producer = $this->contextFor($this->producerUser, $conversation);

        $this->assertSame('ugc', $face['type']);
        $this->assertSame('UGC', $face['type_label']);
        $this->assertSame(['face_receives' => null, 'product_value' => 25000], $face['amounts']);
        $this->assertSame(
            ['cachet' => null, 'service_fee' => null, 'total' => null, 'product_value' => 25000],
            $producer['amounts'],
        );
        $this->assertArrayNotHasKey('commission_ugc', $face);
    }

    public function test_ugc_hybrid_context_per_role(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted, [
            'type_mission' => MissionType::Ugc,
            'type_compensation' => CompensationType::Hybrid,
            'nom_produit' => 'Crème',
            'valeur_produit' => 25000,
            'montant_remuneration' => 20000,
        ]);
        $this->addPaidEntry($conversation, 20000, 17000);

        $face = $this->contextFor($this->faceUser, $conversation);
        $producer = $this->contextFor($this->producerUser, $conversation);

        $this->assertSame(['face_receives' => 17000, 'product_value' => 25000], $face['amounts']);
        $this->assertSame(
            ['cachet' => 20000, 'service_fee' => 2000, 'total' => 22000, 'product_value' => 25000],
            $producer['amounts'],
        );
    }

    /**
     * @return array<string, array{0: CandidatureStatus, 1: bool, 2: string|null, 3: array<string, string>}>
     */
    public static function stepProvider(): array
    {
        return [
            'pending' => [CandidatureStatus::Pending, false, 'requested', ['requested' => 'current', 'accepted' => 'todo', 'paid' => 'todo', 'done' => 'todo']],
            'accepted sans paiement' => [CandidatureStatus::Accepted, false, 'accepted', ['requested' => 'done', 'accepted' => 'current', 'paid' => 'todo', 'done' => 'todo']],
            'accepted avec escrow verrouillé' => [CandidatureStatus::Accepted, true, 'paid', ['requested' => 'done', 'accepted' => 'done', 'paid' => 'current', 'done' => 'todo']],
            'confirmed' => [CandidatureStatus::Confirmed, false, 'paid', ['requested' => 'done', 'accepted' => 'done', 'paid' => 'current', 'done' => 'todo']],
            'in_progress' => [CandidatureStatus::InProgress, false, 'paid', ['requested' => 'done', 'accepted' => 'done', 'paid' => 'current', 'done' => 'todo']],
            'completed' => [CandidatureStatus::Completed, false, 'done', ['requested' => 'done', 'accepted' => 'done', 'paid' => 'done', 'done' => 'current']],
        ];
    }

    /**
     * @dataProvider stepProvider
     *
     * @param  array<string, string>  $expectedStates
     */
    public function test_status_to_step_mapping(CandidatureStatus $status, bool $withLockedEscrow, string $expectedStep, array $expectedStates): void
    {
        $conversation = $this->makeConversation($status);
        if ($withLockedEscrow) {
            $this->addPaidEntry($conversation, 100000, 85000);
        }

        $context = $this->contextFor($this->faceUser, $conversation);

        $this->assertSame($expectedStep, $context['step']['key']);
        $this->assertFalse($context['closed']);
        $this->assertSame(
            ['requested', 'accepted', 'paid', 'done'],
            array_column($context['steps'], 'key'),
        );
        $this->assertSame(['Demandé', 'Accepté', 'Payé', 'Réalisé'], array_column($context['steps'], 'label'));
        $this->assertSame($expectedStates, array_column($context['steps'], 'state', 'key'));
    }

    public function test_rejected_candidature_is_closed_without_current_step(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Rejected);

        $context = $this->contextFor($this->producerUser, $conversation);

        $this->assertTrue($context['closed']);
        $this->assertNull($context['step']);
        $this->assertSame('rejected', $context['candidature_status']);
        $this->assertSame(['todo', 'todo', 'todo', 'todo'], array_column($context['steps'], 'state'));
    }

    public function test_list_exposes_compact_context_without_amounts(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Confirmed, ['date_tournage' => '2030-05-12']);
        $this->addPaidEntry($conversation, 100000, 85000);

        foreach ([[$this->faceUser, 'face'], [$this->producerUser, 'producer']] as [$user, $prefix]) {
            $response = $this->actingAs($user)->getJson("/api/v1/{$prefix}/conversations");
            $response->assertOk();
            $context = $response->json('data.0.context');

            $this->assertSame('mission', $context['type']);
            $this->assertSame('Campagne Karité', $context['title']);
            $this->assertSame('confirmed', $context['candidature_status']);
            $this->assertSame('paid', $context['step']['key']);
            $this->assertSame('2030-05-12', $context['date_tournage']);
            $this->assertArrayNotHasKey('amounts', $context);
            $this->assertArrayNotHasKey('steps', $context);
        }
    }

    public function test_list_context_query_count_does_not_grow_with_conversations(): void
    {
        $first = $this->makeConversation(CandidatureStatus::Confirmed);
        $this->addPaidEntryTo($first);
        Message::factory()->create([
            'conversation_id' => $first->id,
            'sender_id' => $this->producerUser->id,
            'sender_type' => User::class,
        ]);

        $count = function (string $prefix, User $user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->getJson("/api/v1/{$prefix}/conversations")->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $faceBefore = $count('face', $this->faceUser);
        $producerBefore = $count('producer', $this->producerUser);

        for ($i = 0; $i < 5; $i++) {
            $conversation = $this->makeConversation(CandidatureStatus::Confirmed);
            $this->addPaidEntryTo($conversation);
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $this->producerUser->id,
                'sender_type' => User::class,
            ]);
        }

        $this->assertLessThanOrEqual($faceBefore, $count('face', $this->faceUser));
        $this->assertLessThanOrEqual($producerBefore, $count('producer', $this->producerUser));
    }

    private function addPaidEntryTo(Conversation $conversation): void
    {
        $this->addPaidEntry($conversation, 100000, 85000);
    }

    public function test_builder_returns_null_without_linked_candidature(): void
    {
        $conversation = new Conversation;
        $conversation->setRelation('candidature', null);

        $this->assertNull(app(ConversationContextBuilder::class)->build($conversation, $this->faceUser, true));
    }

    public function test_context_does_not_expose_another_producers_conversation(): void
    {
        $otherProducer = Producer::factory()->create();
        $conversation = $this->makeConversation(CandidatureStatus::Accepted, [], null, $otherProducer);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/conversations/{$conversation->uuid}")
            ->assertForbidden();
    }
}
