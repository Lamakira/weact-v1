<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 3.1 — POST /api/v1/producer/candidatures/{candidature}/confirm-shipment :
 * confirmation d'expédition vers une Face engagée (candidature confirmée)
 * d'une mission UGC — un shipment PAR Face engagée (owner = la Candidature,
 * jamais la Mission). Mission auto-close à capacité expédiable (piège n°1).
 */
class UgcMissionShipmentTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    /**
     * Calque exact de UgcMissionAcceptanceTest::makePublishedUgcMission.
     * La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makePaidUgcMission(array $overrides = []): Mission
    {
        return $this->producer->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief détaillé',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    private function makeConfirmedCandidature(Mission $mission, ?Face $face = null): Candidature
    {
        return Candidature::create([
            'face_id' => ($face ?? $this->face)->id, // faces.id (PAS users.id — piège n°2)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function confirmPayload(array $overrides = []): array
    {
        return array_merge([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'note_envoi' => 'Le colis arrive demain entre 14h et 16h.',
        ], $overrides);
    }

    // ===================================================================
    // Happy paths (AC4, AC5, AC6)
    // ===================================================================

    public function test_producer_confirms_shipment_for_confirmed_candidature(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated()
            ->assertJsonPath('data.tunnel_status', UgcTunnelStatus::Shipped->value)
            ->assertJsonPath('data.destinataire.nom', 'Aïcha Bello')
            ->assertJsonPath('data.destinataire.ville', 'Cotonou');

        $this->assertDatabaseHas('shipments', [
            'owner_type' => Candidature::class,
            'owner_id' => $candidature->id,
            'tunnel_status' => UgcTunnelStatus::Shipped->value,
        ]);

        $this->assertSame(CandidatureStatus::Confirmed, $candidature->fresh()->status); // statut owner inchangé (D-3.1.c)
    }

    public function test_confirm_notifies_face_user(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        // Résolution faces.id → users.id (miroir NotifyProducerOnUgcDealAccepted)
        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'ugc_shipment_confirmed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/face/missions/{$mission->uuid}", data_get($notification->data, 'url'));
    }

    public function test_candidature_on_capacity_closed_mission_is_shippable(): void
    {
        // Piège n°1 : l'auto-close à capacité (2.4) clôt la mission alors que
        // les engagements démarrent — PAS de garde Published.
        $mission = $this->makePaidUgcMission(['nombre_faces_voulu' => 1]);
        $candidature = $this->makeConfirmedCandidature($mission);
        $mission->update(['status' => MissionStatus::Closed]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->assertSame(1, Shipment::count());
    }

    public function test_two_confirmed_candidatures_each_get_own_shipment(): void
    {
        // Un colis PAR Face engagée (D-3.1.d) : l'owner est la Candidature,
        // jamais la Mission — 2 engagements = 2 shipments indépendants.
        $mission = $this->makePaidUgcMission();
        $candidature1 = $this->makeConfirmedCandidature($mission);

        $face2 = Face::factory()->create([
            'prenom' => 'Mariam',
            'nom' => 'Soglo',
            'ville' => 'Porto-Novo',
            'pays' => 'Bénin',
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face2->id,
        ]);
        $candidature2 = $this->makeConfirmedCandidature($mission, $face2);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature1->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature2->uuid}/confirm-shipment", $this->confirmPayload(['numero_suivi' => 'GZM-PNV-115872']))
            ->assertCreated()
            ->assertJsonPath('data.destinataire.nom', 'Mariam Soglo')
            ->assertJsonPath('data.destinataire.ville', 'Porto-Novo');

        $this->assertSame(2, Shipment::count());
        $this->assertDatabaseHas('shipments', ['owner_id' => $candidature1->id, 'numero_suivi' => 'GZM-COT-882194']);
        $this->assertDatabaseHas('shipments', ['owner_id' => $candidature2->id, 'numero_suivi' => 'GZM-PNV-115872']);
    }

    // ===================================================================
    // Gardes de statut (AC5)
    // ===================================================================

    public function test_confirm_rejected_for_pending_candidature(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_for_standard_mission_candidature(): void
    {
        // Témoin : une candidature confirmée sur mission standard n'est pas expédiable.
        $mission = $this->makePaidUgcMission([
            'type_mission' => 'publicite',
            'commission_paid_at' => null,
            'type_compensation' => null,
            'nom_produit' => null,
            'valeur_produit' => null,
            'nombre_videos' => null,
            'commission_ugc' => null,
            'budget' => 150000,
        ]);
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_when_mission_commission_unpaid(): void
    {
        // État forgé hors-procédure : mission UGC sans commission encaissée.
        $mission = $this->makePaidUgcMission(['commission_paid_at' => null]);
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_when_mission_refund_requested(): void
    {
        $mission = $this->makePaidUgcMission(['commission_refund_requested_at' => now()]);
        $candidature = $this->makeConfirmedCandidature($mission);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload());

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertSame(0, Shipment::count());
    }

    public function test_confirm_rejected_when_mission_refunded_out_of_band(): void
    {
        // D-2.5.h : refund réglé hors-procédure — commission_refunded_at posé
        // seul, mission restée Published. Symétrique du test booking.
        $mission = $this->makePaidUgcMission(['commission_refunded_at' => now()]);
        $candidature = $this->makeConfirmedCandidature($mission);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload());

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertSame(0, Shipment::count());
    }

    // ===================================================================
    // Idempotence (AC5)
    // ===================================================================

    public function test_reconfirm_returns_already_shipped(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_SHIPPED');

        $this->assertSame(1, Shipment::count());
    }

    // ===================================================================
    // Autorisation (AC7)
    // ===================================================================

    public function test_other_producer_gets_403(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherProducerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_face_gets_403(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    // ===================================================================
    // Exposition resource (AC9)
    // ===================================================================

    public function test_producer_candidatures_index_exposes_shipment(): void
    {
        $mission = $this->makePaidUgcMission();
        $candidature = $this->makeConfirmedCandidature($mission);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/confirm-shipment", $this->confirmPayload())
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.shipment.numero_suivi', 'GZM-COT-882194');
    }
}
