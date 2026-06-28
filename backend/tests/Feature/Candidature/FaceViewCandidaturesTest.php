<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceViewCandidaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

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

    public function test_face_can_view_their_candidatures(): void
    {
        // Create missions and candidatures
        $mission = Mission::factory()
            ->for($this->producer)
            ->published()
            ->create();

        $candidature = Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create(['status' => CandidatureStatus::Pending]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'status_label',
                        'message_motivation',
                        'created_at',
                        'mission' => [
                            'id',
                            'titre',
                            'date_tournage',
                            'lieu',
                            'budget',
                            'type_compensation',
                        ],
                        'producer' => [
                            'id',
                            'display_name',
                            'type',
                            'profile_photo_url',
                        ],
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.id', $candidature->uuid)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.status_label', 'En attente');
    }

    public function test_candidatures_are_paginated_with_15_per_page(): void
    {
        // Create 20 candidatures
        $missions = Mission::factory()
            ->for($this->producer)
            ->published()
            ->count(20)
            ->create();

        foreach ($missions as $mission) {
            Candidature::factory()
                ->for($this->face)
                ->for($mission)
                ->create();
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_can_access_page_2_of_candidatures(): void
    {
        // Create 20 candidatures
        $missions = Mission::factory()
            ->for($this->producer)
            ->published()
            ->count(20)
            ->create();

        foreach ($missions as $mission) {
            Candidature::factory()
                ->for($this->face)
                ->for($mission)
                ->create();
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures?page=2');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_can_filter_candidatures_by_status(): void
    {
        $mission1 = Mission::factory()->for($this->producer)->published()->create();
        $mission2 = Mission::factory()->for($this->producer)->published()->create();
        $mission3 = Mission::factory()->for($this->producer)->published()->create();

        Candidature::factory()->for($this->face)->for($mission1)->create(['status' => CandidatureStatus::Pending]);
        Candidature::factory()->for($this->face)->for($mission2)->create(['status' => CandidatureStatus::Accepted]);
        Candidature::factory()->for($this->face)->for($mission3)->create(['status' => CandidatureStatus::Pending]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $candidature) {
            $this->assertEquals('pending', $candidature['status']);
        }
    }

    public function test_invalid_status_filter_returns_all_candidatures(): void
    {
        $mission1 = Mission::factory()->for($this->producer)->published()->create();
        $mission2 = Mission::factory()->for($this->producer)->published()->create();

        Candidature::factory()->for($this->face)->for($mission1)->create(['status' => CandidatureStatus::Pending]);
        Candidature::factory()->for($this->face)->for($mission2)->create(['status' => CandidatureStatus::Accepted]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures?status=invalid_status');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_candidatures_include_mission_data(): void
    {
        $mission = Mission::factory()
            ->for($this->producer)
            ->published()
            ->create([
                'titre' => 'Test Mission Title',
                'lieu' => 'Cotonou',
                'budget' => 150000,
            ]);

        Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.mission.id', $mission->uuid)
            ->assertJsonPath('data.0.mission.titre', 'Test Mission Title')
            ->assertJsonPath('data.0.mission.lieu', 'Cotonou')
            ->assertJsonPath('data.0.mission.budget', 150000)
            // Mission standard (cash) → type_compensation null (D-8.3.a discriminant)
            ->assertJsonPath('data.0.mission.type_compensation', null);
    }

    public function test_ugc_mission_candidature_exposes_product_compensation_type(): void
    {
        // Branche PORTEUSE du discriminant (D-8.3.a) : une mission UGC produit-seul
        // doit sérialiser type_compensation = 'product' (et pas seulement le null du cas cash) —
        // verrouille la nouvelle ligne resource `$mission?->type_compensation?->value`.
        $mission = Mission::factory()
            ->for($this->producer)
            ->published()
            ->create(['type_compensation' => CompensationType::Product]);

        Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.mission.type_compensation', 'product');
    }

    public function test_accepted_ugc_candidature_exposes_reconfirm_deadline(): void
    {
        // 9-2, D-9.2.a : une candidature UGC `accepted` portant `accepted_at` expose
        // `reconfirm_deadline` = accepted_at + config('ugc.reconfirm_window_hours') en ISO 8601.
        // Fenêtre NON-DÉFAUT (72 ≠ le défaut 48 de la resource) pour prouver la lecture de config :
        // une régression hardcodant addHours(48) échouerait ici.
        config(['ugc.reconfirm_window_hours' => 72]);

        $acceptedAt = now()->startOfSecond();

        $hybridMission = Mission::factory()
            ->for($this->producer)
            ->published()
            ->create(['type_compensation' => CompensationType::Hybrid]);

        $accepted = Candidature::factory()
            ->accepted()
            ->for($this->face)
            ->for($hybridMission)
            // CandidatureFactory::accepted() ne pose PAS accepted_at — explicite ici.
            ->create(['accepted_at' => $acceptedAt]);

        // Une candidature `pending` (sans accepted_at) → reconfirm_deadline null (garde statut).
        $pendingMission = Mission::factory()->for($this->producer)->published()->create();
        $pending = Candidature::factory()
            ->for($this->face)
            ->for($pendingMission)
            ->create(['status' => CandidatureStatus::Pending]);

        // AC5 / clause `accepted_at !== null` : une candidature `Accepted` SANS `accepted_at`
        // (l'effet de bord cash `applySelectionOutcomesOnPaid` flippe Accepted sans ancrer
        // accepted_at) → reconfirm_deadline null. C'est l'exclusion « cash acceptée » de la story ;
        // la factory accepted() ne pose pas accepted_at, donc on ne le passe pas.
        $acceptedNoTimestampMission = Mission::factory()->for($this->producer)->published()->create();
        $acceptedNoTimestamp = Candidature::factory()
            ->accepted()
            ->for($this->face)
            ->for($acceptedNoTimestampMission)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200);

        $byId = collect($response->json('data'))->keyBy('id');

        $expected = $acceptedAt->copy()->addHours(72)->toIso8601String();
        $this->assertSame($expected, $byId[$accepted->uuid]['reconfirm_deadline']);
        $this->assertNull($byId[$pending->uuid]['reconfirm_deadline']);
        $this->assertNull($byId[$acceptedNoTimestamp->uuid]['reconfirm_deadline']);
    }

    public function test_candidatures_include_producer_data(): void
    {
        $producer = Producer::factory()->create([
            'type' => 'agency',
            'agency_name' => 'Test Agency',
        ]);

        $mission = Mission::factory()
            ->for($producer)
            ->published()
            ->create();

        Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.producer.id', $producer->uuid)
            ->assertJsonPath('data.0.producer.display_name', 'Test Agency')
            ->assertJsonPath('data.0.producer.type', 'agency');
    }

    public function test_empty_candidatures_returns_empty_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_motivation_message_is_truncated(): void
    {
        $longMessage = str_repeat('Lorem ipsum dolor sit amet. ', 50); // ~1450 chars

        $mission = Mission::factory()->for($this->producer)->published()->create();
        Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create(['message_motivation' => $longMessage]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200);
        $motivation = $response->json('data.0.message_motivation');
        $this->assertLessThanOrEqual(103, strlen($motivation)); // 100 chars + '...'
        $this->assertStringEndsWith('...', $motivation);
    }

    public function test_producer_cannot_access_face_candidatures(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_candidatures(): void
    {
        $response = $this->getJson('/api/v1/face/candidatures');

        $response->assertStatus(401);
    }

    public function test_candidatures_are_ordered_by_latest_first(): void
    {
        $mission1 = Mission::factory()->for($this->producer)->published()->create();
        $mission2 = Mission::factory()->for($this->producer)->published()->create();
        $mission3 = Mission::factory()->for($this->producer)->published()->create();

        $oldest = Candidature::factory()
            ->for($this->face)
            ->for($mission1)
            ->create(['created_at' => now()->subDays(3)]);

        $middle = Candidature::factory()
            ->for($this->face)
            ->for($mission2)
            ->create(['created_at' => now()->subDays(1)]);

        $newest = Candidature::factory()
            ->for($this->face)
            ->for($mission3)
            ->create(['created_at' => now()]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$newest->uuid, $middle->uuid, $oldest->uuid], $ids);
    }

    public function test_face_only_sees_their_own_candidatures(): void
    {
        // Create another face with candidatures
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $mission = Mission::factory()->for($this->producer)->published()->create();

        // Create candidature for current face
        $myCandidature = Candidature::factory()
            ->for($this->face)
            ->for($mission)
            ->create();

        // Create candidature for other face
        $otherMission = Mission::factory()->for($this->producer)->published()->create();
        Candidature::factory()
            ->for($otherFace)
            ->for($otherMission)
            ->create();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myCandidature->uuid);
    }

    public function test_can_filter_by_all_status_types(): void
    {
        $statuses = [
            CandidatureStatus::Pending,
            CandidatureStatus::Accepted,
            CandidatureStatus::Confirmed,
            CandidatureStatus::InProgress,
            CandidatureStatus::Completed,
            CandidatureStatus::Rejected,
        ];

        foreach ($statuses as $status) {
            $mission = Mission::factory()->for($this->producer)->published()->create();
            Candidature::factory()
                ->for($this->face)
                ->for($mission)
                ->create(['status' => $status]);
        }

        // Test each status filter
        foreach ($statuses as $status) {
            $response = $this->actingAs($this->faceUser)
                ->getJson("/api/v1/face/candidatures?status={$status->value}");

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', $status->value);
        }
    }
}
