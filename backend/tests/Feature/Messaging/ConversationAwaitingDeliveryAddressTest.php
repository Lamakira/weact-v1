<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Drapeau `awaiting_delivery_address` du contexte détaillé : mission UGC, candidature
 * acceptée/confirmée, produit pas encore expédié, contexte non clos.
 */
class ConversationAwaitingDeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Producer $producer;

    private Face $face;

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

    private function makeConversation(CandidatureStatus $status, bool $ugc = true, bool $shipped = false): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'type_mission' => $ugc ? MissionType::Ugc : MissionType::Publicite,
            'type_compensation' => CompensationType::Product,
            'nom_produit' => 'Crème',
            'valeur_produit' => 25000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => $status,
        ]);

        if ($shipped) {
            $candidature->shipment()->create([
                'transporteur' => 'DHL',
                'numero_suivi' => 'TRK123',
                'tunnel_status' => UgcTunnelStatus::Shipped,
                'shipped_at' => now()->subHour(),
                'destinataire_nom' => 'Face Test',
            ]);
        }

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(User $user, Conversation $conversation): array
    {
        $prefix = $user->userable_type === Face::class ? 'face' : 'producer';
        $response = $this->actingAs($user)->getJson("/api/v1/{$prefix}/conversations/{$conversation->uuid}");
        $response->assertOk();

        return $response->json('data.context');
    }

    public function test_ugc_accepted_without_shipment_awaits_address_for_both_roles(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted);

        $this->assertTrue($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
        $this->assertTrue($this->detail($this->producerUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_ugc_confirmed_without_shipment_awaits_address(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Confirmed);

        $this->assertTrue($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_ugc_with_shipment_does_not_await_address(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Confirmed, shipped: true);

        $this->assertFalse($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
        $this->assertFalse($this->detail($this->producerUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_ugc_pending_does_not_await_address(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Pending);

        $this->assertFalse($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_closed_ugc_context_does_not_await_address(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Rejected);

        $this->assertFalse($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_non_ugc_accepted_mission_does_not_await_address(): void
    {
        $conversation = $this->makeConversation(CandidatureStatus::Accepted, ugc: false);

        $this->assertFalse($this->detail($this->faceUser, $conversation)['awaiting_delivery_address']);
        $this->assertFalse($this->detail($this->producerUser, $conversation)['awaiting_delivery_address']);
    }

    public function test_list_compact_context_omits_the_flag(): void
    {
        $this->makeConversation(CandidatureStatus::Accepted);

        foreach ([[$this->faceUser, 'face'], [$this->producerUser, 'producer']] as [$user, $prefix]) {
            $response = $this->actingAs($user)->getJson("/api/v1/{$prefix}/conversations");
            $response->assertOk();
            $this->assertArrayNotHasKey('awaiting_delivery_address', $response->json('data.0.context'));
        }
    }
}
