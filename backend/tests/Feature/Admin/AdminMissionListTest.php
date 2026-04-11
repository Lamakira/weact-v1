<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CandidatureStatus;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMissionListTest extends TestCase
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

    public function test_returns_paginated_list_of_missions(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->count(20)->create(['producer_id' => $producer->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'titre', 'status', 'status_label', 'type_mission', 'type_mission_label', 'lieu', 'candidatures_count', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonCount(15, 'data');
    }

    public function test_search_by_titre_returns_filtered_results(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->create(['producer_id' => $producer->id, 'titre' => 'Pub TV Savon Magique']);
        Mission::factory()->create(['producer_id' => $producer->id, 'titre' => 'Clip Musical Afrobeat']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions?search=Savon');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.titre', 'Pub TV Savon Magique');
    }

    public function test_search_by_lieu_returns_filtered_results(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->create(['producer_id' => $producer->id, 'lieu' => 'Cotonou, Bénin']);
        Mission::factory()->create(['producer_id' => $producer->id, 'lieu' => 'Parakou, Bénin']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions?search=Cotonou');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.lieu', 'Cotonou, Bénin');
    }

    public function test_filter_by_status_returns_only_matching(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->published()->count(2)->create(['producer_id' => $producer->id]);
        Mission::factory()->draft()->count(3)->create(['producer_id' => $producer->id]);
        Mission::factory()->completed()->create(['producer_id' => $producer->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions?status=published');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_by_type_mission_returns_only_matching(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        Mission::factory()->film()->count(2)->create(['producer_id' => $producer->id]);
        Mission::factory()->publicite()->count(3)->create(['producer_id' => $producer->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions?type_mission=film');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_mission_includes_producer_data(): void
    {
        $producer = Producer::factory()->create([
            'agency_name' => 'Studio Lumière',
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Tournage Cotonou',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/missions/{$mission->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'titre', 'description', 'status', 'status_label',
                    'type_mission', 'type_mission_label', 'lieu', 'budget',
                    'candidatures_count', 'producer' => ['id', 'display_name'],
                ],
                'message',
            ])
            ->assertJsonPath('data.titre', 'Tournage Cotonou')
            ->assertJsonPath('data.producer.id', $producer->uuid);
    }

    public function test_show_mission_includes_candidatures_count(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->published()->create(['producer_id' => $producer->id]);

        // Create 3 candidatures for this mission
        $faces = Face::factory()->count(3)->create();
        foreach ($faces as $face) {
            Candidature::factory()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
                'status' => CandidatureStatus::Pending,
            ]);
        }

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/missions/{$mission->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.candidatures_count', 3);
    }

    // ─── AUTH GUARDS ──────────────────────────────────────────────

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/missions');
        $response->assertUnauthorized();

        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create(['producer_id' => $producer->id]);

        $response = $this->getJson("/api/v1/admin/missions/{$mission->uuid}");
        $response->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_admin_missions(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $userToken = $user->createToken('user-token')->plainTextToken;

        $response = $this->withToken($userToken)
            ->getJson('/api/v1/admin/missions');
        $response->assertForbidden();
    }

    // ─── COMBINED FILTERS ────────────────────────────────────────

    public function test_combined_search_status_and_type_filters(): void
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // Target: published film with "Savon" in title
        Mission::factory()->published()->film()->create([
            'producer_id' => $producer->id,
            'titre' => 'Pub Savon Film',
        ]);
        // Decoy: published film without "Savon"
        Mission::factory()->published()->film()->create([
            'producer_id' => $producer->id,
            'titre' => 'Clip Danse',
        ]);
        // Decoy: draft film with "Savon"
        Mission::factory()->draft()->film()->create([
            'producer_id' => $producer->id,
            'titre' => 'Savon Brouillon',
        ]);
        // Decoy: published publicite with "Savon"
        Mission::factory()->published()->publicite()->create([
            'producer_id' => $producer->id,
            'titre' => 'Savon Pub TV',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions?search=Savon&status=published&type_mission=film');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.titre', 'Pub Savon Film');
    }
}
