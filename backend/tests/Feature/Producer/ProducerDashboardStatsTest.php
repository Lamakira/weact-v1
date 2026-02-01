<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerDashboardStatsTest extends TestCase
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

    public function test_authenticated_producer_can_access_dashboard_stats(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'published',
                    'in_progress',
                    'closed',
                    'completed',
                ],
                'message',
            ]);
    }

    public function test_returns_correct_counts_for_each_status(): void
    {
        // Create missions with various statuses for this Producer
        $publishedMissions = Mission::factory()->count(3)->published()->create(['producer_id' => $this->producer->id]);
        $closedMissions = Mission::factory()->count(2)->closed()->create(['producer_id' => $this->producer->id]);
        Mission::factory()->count(5)->completed()->create(['producer_id' => $this->producer->id]);
        // Draft missions should not be counted in display stats
        Mission::factory()->count(1)->draft()->create(['producer_id' => $this->producer->id]);

        // Create a Face and candidatures for in_progress count
        $face = Face::factory()->create();

        // Add confirmed candidature to one closed mission (makes it "in progress")
        Candidature::factory()->confirmed()->create([
            'face_id' => $face->id,
            'mission_id' => $closedMissions[0]->id,
        ]);

        // Add in_progress candidature to one published mission (also counts as "in progress")
        Candidature::factory()->inProgress()->create([
            'face_id' => $face->id,
            'mission_id' => $publishedMissions[0]->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 3)
            ->assertJsonPath('data.in_progress', 2) // 2 missions have confirmed/in_progress candidatures
            ->assertJsonPath('data.closed', 2)
            ->assertJsonPath('data.completed', 5);
    }

    public function test_returns_zeros_for_statuses_with_no_missions(): void
    {
        // Producer has no missions at all
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.closed', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_only_counts_missions_belonging_to_authenticated_producer(): void
    {
        // Create missions for this Producer
        Mission::factory()->count(2)->published()->create(['producer_id' => $this->producer->id]);

        // Create missions for another Producer (should not be counted)
        $otherProducer = Producer::factory()->create();
        Mission::factory()->count(5)->published()->create(['producer_id' => $otherProducer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 2); // Only this Producer's missions
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/producer/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_face_user_gets_403(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Producteurs');
    }

    public function test_draft_missions_are_excluded_from_stats(): void
    {
        // Create only draft missions
        Mission::factory()->count(5)->draft()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.closed', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_response_includes_success_message(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('message', 'Dashboard stats retrieved successfully');
    }

    public function test_in_progress_counts_missions_with_active_candidatures(): void
    {
        $face = Face::factory()->create();

        // Create missions without active candidatures
        $mission1 = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $mission2 = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        // No active candidatures yet - in_progress should be 0
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);

        // Add confirmed candidature - now in_progress should be 1
        Candidature::factory()->confirmed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission1->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 1);

        // Add in_progress candidature to another mission - now in_progress should be 2
        Candidature::factory()->inProgress()->create([
            'face_id' => $face->id,
            'mission_id' => $mission2->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 2);
    }

    public function test_in_progress_does_not_count_pending_or_accepted_candidatures(): void
    {
        $face = Face::factory()->create();
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        // Pending candidature should not count as in_progress
        Candidature::factory()->pending()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);

        // Accepted candidature should not count as in_progress either
        $face2 = Face::factory()->create();
        Candidature::factory()->accepted()->create([
            'face_id' => $face2->id,
            'mission_id' => $mission->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 0);
    }
}
