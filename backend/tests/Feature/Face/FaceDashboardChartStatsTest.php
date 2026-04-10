<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceDashboardChartStatsTest extends TestCase
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

    public function test_authenticated_face_can_access_chart_stats(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'candidatures_by_month',
                    'missions_completed_by_month',
                ],
                'message',
            ]);
    }

    public function test_returns_last_6_months_of_data(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $data = $response->json('data');

        // Should have 7 months (current month + 6 previous months)
        $this->assertGreaterThanOrEqual(6, count($data['candidatures_by_month']));
        $this->assertGreaterThanOrEqual(6, count($data['missions_completed_by_month']));
    }

    public function test_candidatures_by_month_has_correct_structure(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $candidaturesByMonth = $response->json('data.candidatures_by_month');

        // Check first month has all required fields
        $firstMonth = $candidaturesByMonth[0];
        $this->assertArrayHasKey('month', $firstMonth);
        $this->assertArrayHasKey('pending', $firstMonth);
        $this->assertArrayHasKey('accepted', $firstMonth);
        $this->assertArrayHasKey('confirmed', $firstMonth);
        $this->assertArrayHasKey('in_progress', $firstMonth);
        $this->assertArrayHasKey('completed', $firstMonth);
        $this->assertArrayHasKey('rejected', $firstMonth);

        // Month format should be YYYY-MM
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $firstMonth['month']);
    }

    public function test_missions_completed_by_month_has_correct_structure(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $missionsCompletedByMonth = $response->json('data.missions_completed_by_month');

        // Check first month has all required fields
        $firstMonth = $missionsCompletedByMonth[0];
        $this->assertArrayHasKey('month', $firstMonth);
        $this->assertArrayHasKey('count', $firstMonth);

        // Month format should be YYYY-MM
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $firstMonth['month']);
    }

    public function test_returns_correct_candidature_counts_by_month_and_status(): void
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Create candidatures for current month
        Candidature::factory()->count(3)->pending()->create([
            'face_id' => $this->face->id,
            'created_at' => Carbon::now(),
        ]);
        Candidature::factory()->count(2)->accepted()->create([
            'face_id' => $this->face->id,
            'created_at' => Carbon::now(),
        ]);

        // Create candidatures for last month
        Candidature::factory()->count(1)->pending()->create([
            'face_id' => $this->face->id,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $candidaturesByMonth = collect($response->json('data.candidatures_by_month'));

        // Find current month data
        $currentMonthData = $candidaturesByMonth->firstWhere('month', $currentMonth);
        $this->assertEquals(3, $currentMonthData['pending']);
        $this->assertEquals(2, $currentMonthData['accepted']);

        // Find last month data
        $lastMonthData = $candidaturesByMonth->firstWhere('month', $lastMonth);
        $this->assertEquals(1, $lastMonthData['pending']);
    }

    public function test_returns_correct_completed_missions_count_by_month(): void
    {
        $currentMonth = Carbon::now()->format('Y-m');

        // Create completed candidatures for current month
        Candidature::factory()->count(4)->completed()->create([
            'face_id' => $this->face->id,
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $missionsCompletedByMonth = collect($response->json('data.missions_completed_by_month'));

        // Find current month data
        $currentMonthData = $missionsCompletedByMonth->firstWhere('month', $currentMonth);
        $this->assertEquals(4, $currentMonthData['count']);
    }

    public function test_returns_zeros_for_months_with_no_data(): void
    {
        // Face has no candidatures at all
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $candidaturesByMonth = $response->json('data.candidatures_by_month');
        $missionsCompletedByMonth = $response->json('data.missions_completed_by_month');

        // All months should have zero counts
        foreach ($candidaturesByMonth as $monthData) {
            $this->assertEquals(0, $monthData['pending']);
            $this->assertEquals(0, $monthData['accepted']);
            $this->assertEquals(0, $monthData['completed']);
        }

        foreach ($missionsCompletedByMonth as $monthData) {
            $this->assertEquals(0, $monthData['count']);
        }
    }

    public function test_only_counts_candidatures_belonging_to_authenticated_face(): void
    {
        // Create candidatures for this Face
        Candidature::factory()->count(2)->pending()->create([
            'face_id' => $this->face->id,
            'created_at' => Carbon::now(),
        ]);

        // Create candidatures for another Face (should not be counted)
        $otherFace = Face::factory()->create();
        Candidature::factory()->count(5)->pending()->create([
            'face_id' => $otherFace->id,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $currentMonth = Carbon::now()->format('Y-m');
        $candidaturesByMonth = collect($response->json('data.candidatures_by_month'));

        // Find current month data
        $currentMonthData = $candidaturesByMonth->firstWhere('month', $currentMonth);
        $this->assertEquals(2, $currentMonthData['pending']); // Only this Face's candidatures
    }

    public function test_excludes_data_older_than_6_months(): void
    {
        $sevenMonthsAgo = Carbon::now()->subMonths(7);

        // Create candidatures from 7 months ago (should not be included)
        Candidature::factory()->count(3)->pending()->create([
            'face_id' => $this->face->id,
            'created_at' => $sevenMonthsAgo,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $candidaturesByMonth = collect($response->json('data.candidatures_by_month'));

        // The old month should not be in the result
        $oldMonthStr = $sevenMonthsAgo->format('Y-m');
        $oldMonthData = $candidaturesByMonth->firstWhere('month', $oldMonthStr);
        $this->assertNull($oldMonthData);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $response = $this->getJson('/api/v1/face/dashboard/chart-stats');

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
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_response_includes_success_message(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk()
            ->assertJsonPath('message', 'Chart stats retrieved successfully');
    }

    public function test_months_are_ordered_chronologically(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/dashboard/chart-stats');

        $response->assertOk();

        $candidaturesByMonth = $response->json('data.candidatures_by_month');
        $months = array_column($candidaturesByMonth, 'month');

        // Check months are in ascending order
        $sortedMonths = $months;
        sort($sortedMonths);
        $this->assertEquals($sortedMonths, $months);
    }
}
