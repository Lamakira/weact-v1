<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_authenticated_face_can_access_dashboard_stats(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'pending',
                    'accepted',
                    'in_progress',
                    'completed',
                ],
                'message',
            ]);
    }

    public function test_returns_correct_counts_for_each_status(): void
    {
        // Create candidatures with various statuses for this Face
        Candidature::factory()->count(3)->pending()->create(['face_id' => $this->face->id]);
        Candidature::factory()->count(2)->accepted()->create(['face_id' => $this->face->id]);
        Candidature::factory()->count(1)->confirmed()->create(['face_id' => $this->face->id]);
        Candidature::factory()->count(2)->inProgress()->create(['face_id' => $this->face->id]);
        Candidature::factory()->count(5)->completed()->create(['face_id' => $this->face->id]);
        Candidature::factory()->count(1)->rejected()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.pending', 3)
            ->assertJsonPath('data.accepted', 2)
            ->assertJsonPath('data.in_progress', 3) // 1 confirmed + 2 in_progress
            ->assertJsonPath('data.completed', 5);

        // Rejected should NOT be included in any count
    }

    public function test_returns_zeros_for_statuses_with_no_candidatures(): void
    {
        // Face has no candidatures at all
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.accepted', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_only_counts_candidatures_belonging_to_authenticated_face(): void
    {
        // Create candidatures for this Face
        Candidature::factory()->count(2)->pending()->create(['face_id' => $this->face->id]);

        // Create candidatures for another Face (should not be counted)
        $otherFace = Face::factory()->create();
        Candidature::factory()->count(5)->pending()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.pending', 2); // Only this Face's candidatures
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/face/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_producer_user_gets_403(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_confirmed_and_in_progress_are_grouped_together(): void
    {
        // Create only confirmed candidatures
        Candidature::factory()->count(2)->confirmed()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.in_progress', 2);

        // Now add in_progress candidatures
        Candidature::factory()->count(3)->inProgress()->create(['face_id' => $this->face->id]);

        $response2 = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response2->assertOk()
            ->assertJsonPath('data.in_progress', 5); // 2 confirmed + 3 in_progress
    }

    public function test_rejected_candidatures_are_excluded_from_all_stats(): void
    {
        // Create only rejected candidatures
        Candidature::factory()->count(5)->rejected()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.pending', 0)
            ->assertJsonPath('data.accepted', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.completed', 0);
    }

    public function test_response_includes_success_message(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('message', 'Dashboard stats retrieved successfully');
    }
}
