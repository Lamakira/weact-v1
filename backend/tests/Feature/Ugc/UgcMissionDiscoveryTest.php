<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/face/ugc/missions — découverte gated des missions UGC (FR5, UGC 2.1).
 *
 * Face Starter+ active → liste MissionResource complète + meta.can_access_ugc=true ;
 * Face free/expirée → teasers UgcMissionTeaserResource + meta.paywall (200, pas 403).
 */
class UgcMissionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

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
     * La factory Mission ne tire jamais `ugc` — attributs explicites obligatoires.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makePublishedUgcMission(array $overrides = [], ?Producer $producer = null): Mission
    {
        return ($producer ?? $this->producer)->missions()->create(array_merge([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ], $overrides));
    }

    // ===================================================================
    // Face éligible (AC3)
    // ===================================================================

    public function test_starter_face_sees_full_ugc_missions_list(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.can_access_ugc', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'titre', 'description', 'type_mission', 'status', 'producer'],
                ],
                'links',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
        $this->assertArrayNotHasKey('paywall', $response->json('meta'));

        // Internals de facturation Producer : masqués pour les Faces, même abonnées
        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('commission_ugc', $item);
        $this->assertArrayNotHasKey('commission_paid_at', $item);
    }

    public function test_pro_face_sees_full_ugc_missions_list(): void
    {
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->face->id]);
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.can_access_ugc', true)
            ->assertJsonPath('data.0.type_mission', 'ugc');
    }

    public function test_elite_face_sees_full_ugc_missions_list(): void
    {
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $this->face->id]);
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.can_access_ugc', true)
            ->assertJsonPath('data.0.type_mission', 'ugc');
    }

    public function test_list_is_paginated_12_per_page(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        for ($i = 0; $i < 15; $i++) {
            $this->makePublishedUgcMission(['titre' => "Appel UGC {$i}"]);
        }

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_list_is_ordered_by_created_at_desc(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $old = $this->makePublishedUgcMission(['titre' => 'Ancien appel']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $recent = $this->makePublishedUgcMission(['titre' => 'Appel récent']);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.titre', 'Appel récent')
            ->assertJsonPath('data.1.titre', 'Ancien appel');
    }

    public function test_pending_payment_ugc_missions_are_not_listed(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $this->makePublishedUgcMission([
            'status' => MissionStatus::PendingPayment,
            'commission_paid_at' => null,
        ]);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_standard_missions_are_not_listed(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    // ===================================================================
    // Face non éligible — teasers + paywall (AC4)
    // ===================================================================

    public function test_free_face_gets_teasers_without_sensitive_fields(): void
    {
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.can_access_ugc', false)
            ->assertJsonPath('data.0.titre', 'Appel UGC — Unboxing')
            ->assertJsonPath('data.0.type_compensation', 'product')
            ->assertJsonPath('data.0.type_compensation_label', 'Produit seul')
            ->assertJsonPath('data.0.nom_produit', 'Tenue Shade Fit')
            ->assertJsonPath('data.0.valeur_produit', 20000)
            ->assertJsonPath('data.0.nombre_videos', 2)
            ->assertJsonPath('data.0.lieu', 'Cotonou');

        $item = $response->json('data.0');
        foreach (['description', 'profil_recherche', 'montant_remuneration', 'commission_ugc', 'budget', 'producer', 'status', 'genre_voulu', 'duree'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item, "Le teaser ne doit pas fuiter `{$forbidden}`.");
        }

        // Set exact des 10 champs D-2.1.c — toute clé ajoutée au teaser doit être
        // une décision explicite, pas un effet de bord.
        $this->assertEqualsCanonicalizing([
            'id',
            'titre',
            'type_compensation',
            'type_compensation_label',
            'nom_produit',
            'valeur_produit',
            'nombre_videos',
            'lieu',
            'date_limite_candidature',
            'created_at',
        ], array_keys($item), 'Le teaser doit exposer exactement les 10 champs D-2.1.c.');
    }

    public function test_free_face_gets_complete_paywall_meta(): void
    {
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonPath('meta.can_access_ugc', false)
            ->assertJsonPath('meta.paywall.code', 'UGC_SUBSCRIPTION_REQUIRED')
            ->assertJsonPath('meta.paywall.message', "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).")
            ->assertJsonPath('meta.paywall.pricing_url', '/pricing');
    }

    public function test_expired_subscription_face_gets_teasers(): void
    {
        FaceSubscription::factory()->starter()->expired()->create(['face_id' => $this->face->id]);
        $this->makePublishedUgcMission();

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonPath('meta.can_access_ugc', false)
            ->assertJsonPath('meta.paywall.code', 'UGC_SUBSCRIPTION_REQUIRED');
        $this->assertArrayNotHasKey('description', $response->json('data.0'));
    }

    // ===================================================================
    // Filtre producteur is_active (story 3.0)
    // ===================================================================

    public function test_ugc_missions_from_inactive_producer_are_excluded(): void
    {
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $visible = $this->makePublishedUgcMission(['titre' => 'Appel UGC visible']);

        $inactiveProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $inactiveProducer->id,
            'is_active' => false,
        ]);
        $this->makePublishedUgcMission(['titre' => 'Appel UGC masqué'], $inactiveProducer);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->uuid)
            ->assertJsonPath('data.0.titre', 'Appel UGC visible')
            // Le teaser expose aussi id+titre : seul meta.can_access_ugc
            // prouve que ce test exerce bien la branche éligible (review 3.0).
            ->assertJsonPath('meta.can_access_ugc', true);
    }

    public function test_teaser_list_excludes_inactive_producer_missions(): void
    {
        // Face free : le filtre s'applique au niveau query, donc la branche
        // teaser paywall est couverte au même titre que la branche éligible.
        $visible = $this->makePublishedUgcMission(['titre' => 'Appel UGC visible']);

        $inactiveProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $inactiveProducer->id,
            'is_active' => false,
        ]);
        $this->makePublishedUgcMission(['titre' => 'Appel UGC masqué'], $inactiveProducer);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/ugc/missions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.can_access_ugc', false)
            ->assertJsonPath('data.0.id', $visible->uuid)
            ->assertJsonPath('data.0.titre', 'Appel UGC visible');
    }

    // ===================================================================
    // Gardes rôle / auth (AC8)
    // ===================================================================

    public function test_producer_cannot_access_ugc_discovery(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/ugc/missions')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_ugc_discovery(): void
    {
        $this->getJson('/api/v1/face/ugc/missions')
            ->assertStatus(401);
    }
}
