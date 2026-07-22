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

    private function seedGeneration(int $faceId, string $createdAt, string $source = 'nightly', int $generation = 1): void
    {
        DB::table('face_listing_ranks')->insert([
            'generation' => $generation,
            'face_id' => $faceId,
            'rank' => 1,
            'created_at' => $createdAt,
            'source' => $source,
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

    public function test_recent_tick_generations_do_not_mask_a_stale_nightly_base(): void
    {
        // THE regression this feature could introduce: the carousel writes a
        // generation every few minutes, so a watchdog measuring MAX(created_at)
        // over the whole table would report "fresh" forever while the nightly
        // rebuild is dead. The rotation only permutes the last nightly base —
        // it can never repair staleness.
        $face = Face::factory()->withActiveUser()->create();
        $this->seedGeneration($face->id, now()->subHours(72)->toDateTimeString(), 'nightly', 1);
        $this->seedGeneration($face->id, now()->subMinutes(2)->toDateTimeString(), 'tick', 2);

        Log::shouldReceive('critical')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'stale'));

        $this->artisan('faces:check-listing-ranks-freshness')->assertExitCode(1);
    }

    public function test_tick_generations_alone_are_not_a_built_ranking(): void
    {
        // Only `source = nightly` proves the fairness rebuild ran: ranks with
        // no identifiable nightly base (legacy rows, or ticks only) must read
        // as "never built", not as a healthy ranking.
        $face = Face::factory()->withActiveUser()->create();
        $this->seedGeneration($face->id, now()->subMinutes(2)->toDateTimeString(), 'tick', 1);

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

        $this->assertNotNull(
            DB::table('face_listing_ranks')->where('source', 'nightly')->max('created_at'),
        );

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
