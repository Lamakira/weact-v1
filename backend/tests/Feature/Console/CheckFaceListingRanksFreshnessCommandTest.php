<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Face;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckFaceListingRanksFreshnessCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedGeneration(int $faceId, string $createdAt): void
    {
        DB::table('face_listing_ranks')->insert([
            'generation' => 1,
            'face_id' => $faceId,
            'rank' => 1,
            'created_at' => $createdAt,
        ]);
    }

    public function test_fresh_generation_passes_silently(): void
    {
        $face = Face::factory()->withActiveUser()->create();
        $this->seedGeneration($face->id, now()->subHours(20)->toDateTimeString());

        Log::shouldReceive('critical')->never();

        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(0);
    }

    public function test_stale_generation_fires_critical_and_fails(): void
    {
        $face = Face::factory()->withActiveUser()->create();
        $this->seedGeneration($face->id, now()->subHours(49)->toDateTimeString());

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'stale'));

        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(1);
    }

    public function test_empty_ranks_with_no_listable_faces_is_healthy(): void
    {
        // Empty platform (or every account deactivated): nothing to rank,
        // an empty table is the expected state — no alert.
        Log::shouldReceive('critical')->never();

        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(0);
    }

    public function test_empty_ranks_with_listable_faces_fires_never_built_alert(): void
    {
        Face::factory()->withActiveUser()->create();

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'never been built'));

        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(1);
    }

    public function test_rebuild_stamps_the_generation_birth_timestamp(): void
    {
        // The watchdog's signal is written by the rebuild itself: a fresh
        // rebuild must always satisfy the freshness check.
        Face::factory()->withActiveUser()->create();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->assertNotNull(DB::table('face_listing_ranks')->max('created_at'));

        Log::shouldReceive('critical')->never();
        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(0);
    }

    public function test_freshness_watchdog_is_scheduled_hourly(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())->first(
            fn ($e) => str_contains($e->command ?? '', 'faces:check-listing-ranks-freshness'),
        );

        $this->assertNotNull($event, 'CheckFaceListingRanksFreshnessCommand is not scheduled.');
        $this->assertSame('0 * * * *', $event->expression, 'Watchdog must run hourly.');
    }
}
