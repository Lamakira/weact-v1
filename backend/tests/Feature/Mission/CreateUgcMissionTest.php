<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUgcMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidUgcMissionData(): array
    {
        return [
            'titre' => 'Appel UGC — Unboxing Tenue Shade Fit',
            'description' => 'Nous cherchons des créatrices pour un unboxing + avis de notre nouvelle tenue.',
            'date_tournage' => now()->addMonth()->format('Y-m-d'),
            'profil_recherche' => 'Créatrices lifestyle, lumière naturelle, voix off FR',
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou, Bénin',
            'duree' => 'Livrables vidéo (Unboxing + Avis)',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000, // → commission plancher 2500
            // PAS de 'budget' : dérivé serveur (D-1.3.b)
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidUgcHybridMissionData(): array
    {
        return array_merge($this->getValidUgcMissionData(), [
            'type_compensation' => 'hybrid',
            'valeur_produit' => 50000,        // → commission 5000
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,  // → budget dérivé 15000
        ]);
    }

    public function test_server_ignores_client_submitted_commission(): void
    {
        $data = $this->getValidUgcMissionData();
        $data['commission_ugc'] = 999999; // tentative client falsifiée

        // serveur recalcule (valeur_produit 20000 → 2500), valeur client ignorée
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data)
            ->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2500);

        $this->assertDatabaseMissing('missions', ['commission_ugc' => 999999]);
    }

    public function test_producer_can_create_ugc_product_only_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $this->getValidUgcMissionData());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.status_label', 'En attente de paiement')
            ->assertJsonPath('data.type_mission', 'ugc')
            ->assertJsonPath('data.type_mission_label', 'UGC')
            ->assertJsonPath('data.type_compensation', 'product')
            ->assertJsonPath('data.type_compensation_label', 'Produit seul')
            ->assertJsonPath('data.nom_produit', 'Tenue Shade Fit')
            ->assertJsonPath('data.valeur_produit', 20000)
            ->assertJsonPath('data.nombre_videos', 2)
            ->assertJsonPath('data.commission_ugc', 2500)
            ->assertJsonPath('data.montant_remuneration', null)
            ->assertJsonPath('data.budget', 0)
            ->assertJsonPath('message', 'Mission UGC créée. Réglez la commission pour la publier.');

        $this->assertDatabaseHas('missions', [
            'producer_id' => $this->producer->id,
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'montant_remuneration' => null,
            'budget' => 0,
            'status' => 'pending_payment',
        ]);
    }

    public function test_producer_can_create_ugc_hybrid_mission(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $this->getValidUgcHybridMissionData());

        $response->assertCreated()
            ->assertJsonPath('data.type_compensation', 'hybrid')
            ->assertJsonPath('data.type_compensation_label', 'Produit + argent')
            ->assertJsonPath('data.valeur_produit', 50000)
            ->assertJsonPath('data.nombre_videos', 3)
            ->assertJsonPath('data.commission_ugc', 5000)
            ->assertJsonPath('data.montant_remuneration', 15000)
            ->assertJsonPath('data.budget', 15000)
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertDatabaseHas('missions', [
            'producer_id' => $this->producer->id,
            'type_mission' => 'ugc',
            'type_compensation' => 'hybrid',
            'commission_ugc' => 5000,
            'montant_remuneration' => 15000,
            'budget' => 15000,
            'nombre_videos' => 3,
            'status' => 'pending_payment',
        ]);
    }

    public function test_ugc_product_only_forces_video_count_to_2(): void
    {
        $data = $this->getValidUgcMissionData();
        $data['nombre_videos'] = 5; // client tente d'imposer 5 en produit seul

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertCreated()
            ->assertJsonPath('data.nombre_videos', 2);

        $this->assertDatabaseHas('missions', [
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'nombre_videos' => 2,
        ]);
    }

    public function test_ugc_mission_does_not_require_budget(): void
    {
        $data = $this->getValidUgcMissionData();
        unset($data['budget']); // déjà absent, on s'assure qu'aucun budget n'est exigé

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        // Prouve que `budget` n'est pas requis pour l'UGC (dérivé serveur à 0).
        $response->assertCreated()
            ->assertJsonPath('data.budget', 0);
    }

    public function test_ugc_hybrid_requires_remuneration(): void
    {
        $data = $this->getValidUgcHybridMissionData();
        unset($data['montant_remuneration']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('montant_remuneration');
    }

    public function test_ugc_hybrid_requires_nombre_videos(): void
    {
        $data = $this->getValidUgcHybridMissionData();
        unset($data['nombre_videos']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('nombre_videos');
    }

    public function test_ugc_requires_valeur_produit(): void
    {
        $data = $this->getValidUgcMissionData();
        unset($data['valeur_produit']);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('valeur_produit');
    }

    public function test_ugc_requires_valid_type_compensation(): void
    {
        $data = $this->getValidUgcMissionData();
        $data['type_compensation'] = 'invalid';

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type_compensation');
    }

    public function test_ugc_product_only_ignores_client_supplied_financial_fields(): void
    {
        // AC3 / autorité serveur : le client tente d'imposer des montants financiers.
        // Tous sont absents de validated() (aucune règle UGC produit seul) et écrasés serveur.
        $data = $this->getValidUgcMissionData();
        $data['commission_ugc'] = 1;            // tentative de sous-payer la commission WeAct
        $data['montant_remuneration'] = 99999;  // ignoré en produit seul (jamais hybride)
        $data['budget'] = 99999;                // tentative d'imposer un budget (dérivé serveur)

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $data);

        // valeur_produit=20000 → commission plancher 2500, recalculée serveur.
        $response->assertCreated()
            ->assertJsonPath('data.commission_ugc', 2500)
            ->assertJsonPath('data.montant_remuneration', null)
            ->assertJsonPath('data.budget', 0);

        $this->assertDatabaseHas('missions', [
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'commission_ugc' => 2500,
            'montant_remuneration' => null,
            'budget' => 0,
        ]);

        // Le montant frauduleux ne doit jamais être persisté.
        $this->assertDatabaseMissing('missions', [
            'commission_ugc' => 1,
        ]);
    }

    public function test_ugc_mission_not_visible_in_public_listing(): void
    {
        // Une mission standard publiée (visible) sert de témoin pour prouver que
        // le listing fonctionne et exclut uniquement la mission UGC pending_payment.
        Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'date_limite_candidature' => now()->addWeek(),
        ]);

        $createResponse = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', $this->getValidUgcMissionData());

        $createResponse->assertCreated();
        $ugcUuid = $createResponse->json('data.id');

        // Route publique, sans authentification.
        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')                  // seule la mission standard publiée
            ->assertJsonMissing(['id' => $ugcUuid]);      // la mission UGC pending_payment est absente
    }
}
