<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardRecentActivityTest extends TestCase
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

    public function test_admin_can_get_recent_activity(): void
    {
        $producer = Producer::factory()->create();
        Mission::factory()->published()->create(['producer_id' => $producer->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['type', 'title', 'description', 'timestamp', 'link'],
                ],
                'message',
            ]);
    }

    public function test_recent_activity_returns_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission Alpha',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        $missionEvents = collect($data)->where('type', 'mission');
        $this->assertGreaterThanOrEqual(1, $missionEvents->count());

        $missionEvent = $missionEvents->first();
        $this->assertEquals('Test Mission Alpha', $missionEvent['title']);
        $this->assertArrayHasKey('timestamp', $missionEvent);
        $this->assertArrayHasKey('link', $missionEvent);
    }

    public function test_recent_activity_returns_articles(): void
    {
        Article::factory()->published()->create([
            'admin_id' => $this->admin->id,
            'title' => 'Test Article Beta',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        $articleEvents = collect($data)->where('type', 'article');
        $this->assertGreaterThanOrEqual(1, $articleEvents->count());

        $articleEvent = $articleEvents->first();
        $this->assertEquals('Test Article Beta', $articleEvent['title']);
    }

    public function test_recent_activity_returns_user_registrations(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        $userEvents = collect($data)->where('type', 'user');
        $this->assertGreaterThanOrEqual(1, $userEvents->count());
    }

    public function test_recent_activity_returns_max_5_items(): void
    {
        $producer = Producer::factory()->create();

        // Create 10 missions — only 5 should be returned at most
        Mission::factory()->count(10)->published()->create([
            'producer_id' => $producer->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertLessThanOrEqual(5, count($data));
    }

    public function test_recent_activity_is_sorted_by_most_recent(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'titre' => 'Old Mission',
            'created_at' => now()->subDays(5),
        ]);

        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'titre' => 'Recent Mission',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        // First item should be more recent than the second
        if (count($data) >= 2) {
            $this->assertGreaterThanOrEqual(
                $data[1]['timestamp'],
                $data[0]['timestamp']
            );
        }
    }

    public function test_recent_activity_empty_platform(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function test_unauthenticated_user_cannot_access_recent_activity(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/recent-activity');
        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_access_recent_activity(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('user-token')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertStatus(403);
    }
}
