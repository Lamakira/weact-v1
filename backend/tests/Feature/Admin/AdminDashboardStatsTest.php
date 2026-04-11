<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CandidatureStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
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

    public function test_admin_can_get_dashboard_stats(): void
    {
        // Seed data: 3 faces, 2 producers, 1 admin (the test admin)
        Face::factory()->count(3)->create();
        Producer::factory()->count(2)->create();

        // Missions: 2 published, 1 closed, 1 completed, 1 draft
        $producer = Producer::factory()->create();
        Mission::factory()->count(2)->published()->create(['producer_id' => $producer->id]);
        Mission::factory()->closed()->create(['producer_id' => $producer->id]);
        Mission::factory()->completed()->create(['producer_id' => $producer->id]);
        Mission::factory()->draft()->create(['producer_id' => $producer->id]);

        // Articles: 3 published, 2 draft
        Article::factory()->count(3)->published()->create(['admin_id' => $this->admin->id]);
        Article::factory()->count(2)->draft()->create(['admin_id' => $this->admin->id]);

        // Candidatures: 4 total, 2 completed
        $face = Face::factory()->create();
        $missions = Mission::factory()->count(4)->published()->create(['producer_id' => $producer->id]);
        foreach ($missions as $mission) {
            Candidature::factory()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
                'status' => CandidatureStatus::Pending,
            ]);
        }
        // Mark 2 as completed
        Candidature::take(2)->get()->each(function ($candidature) {
            $candidature->update(['status' => CandidatureStatus::Completed]);
        });

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'users' => ['faces', 'producers', 'admins'],
                    'missions' => ['published', 'closed', 'completed', 'draft'],
                    'articles' => ['published', 'draft'],
                    'candidatures' => ['total', 'completed'],
                ],
                'message',
            ]);

        // Verify user counts (3 initial + 1 from candidature seeding = 4 faces, 2 initial + 1 producer = 3 producers)
        $data = $response->json('data');
        $this->assertEquals(4, $data['users']['faces']);
        $this->assertEquals(3, $data['users']['producers']);
        $this->assertEquals(1, $data['users']['admins']);

        // Verify mission counts (2 + 4 published, 1 closed, 1 completed, 1 draft)
        $this->assertEquals(6, $data['missions']['published']);
        $this->assertEquals(1, $data['missions']['closed']);
        $this->assertEquals(1, $data['missions']['completed']);
        $this->assertEquals(1, $data['missions']['draft']);

        // Verify article counts
        $this->assertEquals(3, $data['articles']['published']);
        $this->assertEquals(2, $data['articles']['draft']);

        // Verify candidature counts
        $this->assertEquals(4, $data['candidatures']['total']);
        $this->assertEquals(2, $data['candidatures']['completed']);
    }

    public function test_admin_dashboard_stats_with_empty_platform(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.users.faces', 0)
            ->assertJsonPath('data.users.producers', 0)
            ->assertJsonPath('data.users.admins', 1) // The test admin itself
            ->assertJsonPath('data.missions.published', 0)
            ->assertJsonPath('data.missions.closed', 0)
            ->assertJsonPath('data.missions.completed', 0)
            ->assertJsonPath('data.missions.draft', 0)
            ->assertJsonPath('data.articles.published', 0)
            ->assertJsonPath('data.articles.draft', 0)
            ->assertJsonPath('data.candidatures.total', 0)
            ->assertJsonPath('data.candidatures.completed', 0);
    }

    public function test_admin_dashboard_stats_returns_correct_message(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Admin dashboard stats retrieved successfully');
    }

    public function test_unauthenticated_user_cannot_access_dashboard_stats(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_dashboard_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('user-token')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertStatus(403);
    }
}
