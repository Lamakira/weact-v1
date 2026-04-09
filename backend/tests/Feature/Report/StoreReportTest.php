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

    public function test_duplicate_report_returns_success_message_and_does_not_create_new_row(): void
    {
        $user = User::factory()->create();
        $mission = Mission::factory()->create();

        Report::query()->create([
            'reporter_id' => $user->id,
            'reportable_type' => Mission::class,
            'reportable_id' => $mission->id,
            'reason' => 'fraude',
            'description' => null,
            'status' => 'pending',
        ]);

        $payload = [
            'reportable_type' => 'mission',
            'reportable_id' => $mission->id,
            'reason' => 'autre',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reports', $payload);

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Vous avez déjà signalé ce contenu. Nous l\'examinerons dans les meilleurs délais.'
            );

        $this->assertSame(1, Report::query()->count());
    }

    public function test_user_can_create_report_with_uuid_identifier(): void
    {
        $user = User::factory()->create();
        $mission = Mission::factory()->create();

        $payload = [
            'reportable_type' => 'mission',
            'reportable_id' => $mission->uuid,
            'reason' => 'autre',
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/reports', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.id', fn (int $id): bool => $id > 0);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => Mission::class,
            'reportable_id' => $mission->id,
            'reason' => 'autre',
        ]);
    }
}
