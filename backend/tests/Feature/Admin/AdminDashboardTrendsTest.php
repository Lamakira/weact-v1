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

class AdminDashboardTrendsTest extends TestCase
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

    public function test_admin_can_get_dashboard_trends(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'registrations' => ['faces_7d', 'faces_30d', 'producers_7d', 'producers_30d'],
                    'missions' => ['published_7d', 'published_30d', 'completed_7d', 'completed_30d'],
                    'candidatures' => ['submitted_7d', 'submitted_30d', 'acceptance_rate_30d'],
                    'health' => ['missions_without_candidatures_30d', 'active_users_7d'],
                ],
                'message',
            ]);
    }

    public function test_trends_returns_correct_registration_counts(): void
    {
        // Create 2 faces today, 1 face 20 days ago
        Face::factory()->count(2)->create();
        Face::factory()->create(['created_at' => now()->subDays(20)]);

        // Create 1 producer today, 1 producer 10 days ago
        Producer::factory()->create();
        Producer::factory()->create(['created_at' => now()->subDays(10)]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(2, $data['registrations']['faces_7d']);
        $this->assertEquals(3, $data['registrations']['faces_30d']);
        $this->assertEquals(1, $data['registrations']['producers_7d']);
        $this->assertEquals(2, $data['registrations']['producers_30d']);
    }

    public function test_trends_returns_correct_mission_counts(): void
    {
        $producer = Producer::factory()->create();

        // 1 published today, 1 published 15 days ago
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
        ]);
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'created_at' => now()->subDays(15),
        ]);

        // 1 completed today
        Mission::factory()->completed()->create([
            'producer_id' => $producer->id,
        ]);

        // 1 published 60 days ago (should NOT appear in 30d)
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'created_at' => now()->subDays(60),
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $data = $response->json('data');

        $this->assertEquals(1, $data['missions']['published_7d']);
        $this->assertEquals(2, $data['missions']['published_30d']);
        $this->assertEquals(1, $data['missions']['completed_7d']);
        $this->assertEquals(1, $data['missions']['completed_30d']);
    }

    public function test_trends_returns_correct_candidature_stats(): void
    {
        $producer = Producer::factory()->create();
        $face = Face::factory()->create();

        // Create 3 candidatures in last 7 days: 2 accepted, 1 pending
        $missions = Mission::factory()->count(3)->published()->create([
            'producer_id' => $producer->id,
        ]);

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $missions[0]->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $missions[1]->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $missions[2]->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $data = $response->json('data');

        $this->assertEquals(3, $data['candidatures']['submitted_7d']);
        $this->assertEquals(3, $data['candidatures']['submitted_30d']);
        // 2 accepted out of 3 = 66.7%
        $this->assertEquals(66.7, $data['candidatures']['acceptance_rate_30d']);
    }

    public function test_trends_returns_missions_without_candidatures(): void
    {
        $producer = Producer::factory()->create();
        $face = Face::factory()->create();

        // 2 published missions: 1 with candidature, 1 without
        $missionWithCandidature = Mission::factory()->published()->create([
            'producer_id' => $producer->id,
        ]);
        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
        ]);

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $missionWithCandidature->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $data = $response->json('data');
        $this->assertEquals(1, $data['health']['missions_without_candidatures_30d']);
    }

    public function test_trends_empty_platform_returns_zeros(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(0, $data['registrations']['faces_7d']);
        $this->assertEquals(0, $data['registrations']['faces_30d']);
        $this->assertEquals(0, $data['registrations']['producers_7d']);
        $this->assertEquals(0, $data['registrations']['producers_30d']);
        $this->assertEquals(0, $data['missions']['published_7d']);
        $this->assertEquals(0, $data['missions']['completed_7d']);
        $this->assertEquals(0.0, $data['candidatures']['acceptance_rate_30d']);
        $this->assertEquals(0, $data['health']['missions_without_candidatures_30d']);
        $this->assertEquals(1, $data['health']['active_users_7d']);
    }

    public function test_unauthenticated_user_cannot_access_trends(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/trends');
        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_trends(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('user-token')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $response->assertStatus(403);
    }
}
