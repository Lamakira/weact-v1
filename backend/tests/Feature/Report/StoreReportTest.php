<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Models\Mission;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_report_without_optional_description(): void
    {
        $user = User::factory()->create();
        $mission = Mission::factory()->create();

        $payload = [
            'reportable_type' => 'mission',
            'reportable_id' => $mission->id,
            'reason' => 'autre',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reports', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.id', fn (int $id): bool => $id > 0);

        $report = Report::query()->first();

        $this->assertNotNull($report);
        $this->assertNull($report->description);
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'reporter_id' => $user->id,
            'reportable_type' => Mission::class,
            'reportable_id' => $mission->id,
            'reason' => 'autre',
            'description' => null,
        ]);
    }
}
