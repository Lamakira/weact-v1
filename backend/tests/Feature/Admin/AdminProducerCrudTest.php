<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\ProducerType;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProducerCrudTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    // ─── INDEX (LIST) ─────────────────────────────────────────────

    public function test_returns_paginated_list_of_producers(): void
    {
        Producer::factory()->count(3)->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'agency_name', 'first_name', 'last_name', 'display_name', 'bio', 'average_rating', 'ratings_count', 'missions_count', 'email', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_search_by_first_name_returns_filtered_results(): void
    {
        Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'type' => ProducerType::Particulier]);
        Producer::factory()->create(['first_name' => 'Paul', 'last_name' => 'Martin', 'type' => ProducerType::Particulier]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers?search=Jean');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.first_name', 'Jean');
    }

    public function test_search_by_last_name_returns_filtered_results(): void
    {
        Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'type' => ProducerType::Particulier]);
        Producer::factory()->create(['first_name' => 'Paul', 'last_name' => 'Martin', 'type' => ProducerType::Particulier]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers?search=Dupont');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.last_name', 'Dupont');
    }

    public function test_search_by_agency_name_returns_filtered_results(): void
    {
        Producer::factory()->agency()->create(['agency_name' => 'Studio Alpha']);
        Producer::factory()->agency()->create(['agency_name' => 'Studio Beta']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers?search=Alpha');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.agency_name', 'Studio Alpha');
    }

    public function test_search_by_email_returns_filtered_results(): void
    {
        $producer1 = Producer::factory()->create();
        User::factory()->create([
            'email' => 'unique-producer-search@example.com',
            'userable_type' => Producer::class,
            'userable_id' => $producer1->id,
        ]);
        $producer2 = Producer::factory()->create();
        User::factory()->create([
            'email' => 'other-producer@example.com',
            'userable_type' => Producer::class,
            'userable_id' => $producer2->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers?search=unique-producer-search');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_by_type_returns_correct_producers(): void
    {
        Producer::factory()->agency()->create();
        Producer::factory()->individual()->create();
        Producer::factory()->agency()->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers?type=agency');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_returns_single_producer_with_full_detail(): void
    {
        $producer = Producer::factory()->agency()->create([
            'agency_name' => 'Studio Test',
            'bio' => 'Test bio',
        ]);
        $user = User::factory()->create([
            'email' => 'studio-test@example.com',
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'type', 'agency_name', 'first_name', 'last_name', 'display_name', 'bio', 'average_rating', 'ratings_count', 'missions_count', 'email'],
                'message',
            ])
            ->assertJsonPath('data.agency_name', 'Studio Test')
            ->assertJsonPath('data.bio', 'Test bio')
            ->assertJsonPath('data.type', 'agency')
            ->assertJsonPath('data.email', 'studio-test@example.com')
            ->assertJsonPath('data.missions_count', 0);
    }

    public function test_show_returns_404_for_nonexistent_producer(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers/99999');

        $response->assertNotFound();
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_update_producer_fields_successfully(): void
    {
        $producer = Producer::factory()->individual()->create([
            'first_name' => 'OldFirst',
            'last_name' => 'OldLast',
            'bio' => 'Old bio',
        ]);
        User::factory()->create([
            'email' => 'producer-admin@example.com',
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Mission mise a jour',
            'status' => MissionStatus::Published,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/producers/{$producer->id}", [
                'first_name' => 'NewFirst',
                'last_name' => 'NewLast',
                'bio' => 'Updated bio',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'NewFirst')
            ->assertJsonPath('data.last_name', 'NewLast')
            ->assertJsonPath('data.bio', 'Updated bio')
            ->assertJsonPath('data.email', 'producer-admin@example.com')
            ->assertJsonPath('data.missions_count', 1)
            ->assertJsonCount(1, 'data.missions')
            ->assertJsonPath('data.missions.0.title', 'Mission mise a jour')
            ->assertJsonPath('message', 'Profil Producteur mis à jour avec succès');

        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'first_name' => 'NewFirst',
            'last_name' => 'NewLast',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_update_with_invalid_data_returns_422(): void
    {
        $producer = Producer::factory()->create();

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/producers/{$producer->id}", [
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
    }

    // ─── DESTROY ──────────────────────────────────────────────────

    public function test_delete_producer_and_associated_user(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Profil Producteur supprimé avec succès');

        $this->assertDatabaseMissing('producers', ['id' => $producer->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_blocked_by_active_missions(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'active_missions')
            ->assertJsonPath('error.message', 'Impossible de supprimer ce producteur. Des missions actives existent.');

        $this->assertDatabaseHas('producers', ['id' => $producer->id]);
    }

    public function test_delete_blocked_by_active_candidatures(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);
        $face = Face::factory()->create();
        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'active_candidatures')
            ->assertJsonPath('error.message', 'Impossible de supprimer ce producteur. Des candidatures en cours existent.');

        $this->assertDatabaseHas('producers', ['id' => $producer->id]);
    }

    public function test_delete_allowed_with_completed_missions_only(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('producers', ['id' => $producer->id]);
    }

    // ─── SHOW DETAIL (Story 13-7) ────────────────────────────────

    public function test_show_producer_includes_missions_array(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'titre' => 'Mission Alpha',
        ]);
        Mission::factory()->completed()->create([
            'producer_id' => $producer->id,
            'titre' => 'Mission Beta',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'missions' => [
                        ['id', 'title', 'status', 'status_label', 'created_at'],
                    ],
                ],
            ])
            ->assertJsonCount(2, 'data.missions');
    }

    // ─── AUTH GUARDS ──────────────────────────────────────────────

    public function test_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/admin/producers');
        $response->assertUnauthorized();
    }

    public function test_returns_403_for_non_admin_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/producers');

        $response->assertForbidden();
    }
}
