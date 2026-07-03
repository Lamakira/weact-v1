<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebuildFaceListingRanksCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeFace(array $attributes = []): Face
    {
        $face = Face::factory()->create($attributes);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return $face;
    }

    /**
     * @return list<int> face IDs of the given generation, best rank first
     */
    private function generationOrder(int $generation): array
    {
        return DB::table('face_listing_ranks')
            ->where('generation', $generation)
            ->orderBy('rank')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function test_writes_a_complete_generation_with_dense_unique_ranks(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->makeFace();
        }

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $rows = DB::table('face_listing_ranks')->where('generation', 1)->orderBy('rank')->get();

        $this->assertCount(8, $rows);
        // Dense 1..N ranks, one row per Face.
        $this->assertSame(range(1, 8), $rows->pluck('rank')->map(fn ($r) => (int) $r)->all());
        $this->assertSame(
            Face::query()->orderBy('id')->pluck('id')->all(),
            $rows->pluck('face_id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
        );
    }

    public function test_excludes_faces_of_inactive_users_from_the_generation(): void
    {
        $active = $this->makeFace();

        $inactive = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $inactive->id,
            'is_active' => false,
        ]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->assertSame([$active->id], $this->generationOrder(1));
    }

    public function test_tier_queues_follow_active_subscription_plan(): void
    {
        $free = $this->makeFace();
        $elite = $this->makeFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);
        $expiredElite = $this->makeFace();
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $expiredElite->id]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // Slot 1 goes to the élite queue (weight 60 wins the first WRR slot);
        // the expired élite ranks in the free queue behind the older free Face.
        $this->assertSame([$elite->id, $free->id, $expiredElite->id], $this->generationOrder(1));
    }

    public function test_featured_and_photo_less_rules_shape_each_tier_queue(): void
    {
        // All free tier: sequence = free queue order.
        $photoLess = $this->makeFace(['profile_photo' => null]);
        $withPhoto = $this->makeFace(['profile_photo' => 'p.jpg']);
        $featured = $this->makeFace(['is_featured' => true, 'profile_photo' => null]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->assertSame(
            [$featured->id, $withPhoto->id, $photoLess->id],
            $this->generationOrder(1),
        );
    }

    public function test_stamps_last_page1_exposed_at_for_the_first_fifteen_only(): void
    {
        $faces = [];
        for ($i = 0; $i < 20; $i++) {
            $faces[] = $this->makeFace();
        }

        // Freeze updated_at to detect any bump by the stamp below.
        $frozenUpdatedAt = '2026-01-01 00:00:00';
        DB::table('faces')->update(['updated_at' => $frozenUpdatedAt]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $order = $this->generationOrder(1);
        $pageOne = array_slice($order, 0, 15);
        $rest = array_slice($order, 15);

        $this->assertSame(
            15,
            Face::whereIn('id', $pageOne)->whereNotNull('last_page1_exposed_at')->count(),
            'The 15 best-ranked Faces must be stamped as page-1 exposed.',
        );
        $this->assertSame(
            count($rest),
            Face::whereIn('id', $rest)->whereNull('last_page1_exposed_at')->count(),
            'Faces beyond the page-1 window must NOT be stamped.',
        );
        $this->assertSame(
            20,
            Face::where('updated_at', $frozenUpdatedAt)->count(),
            'The LRU stamp is bookkeeping: it must not bump any Face updated_at.',
        );
    }

    public function test_skips_without_writing_when_another_rebuild_holds_the_lock(): void
    {
        $this->makeFace();

        $lock = Cache::lock('faces:rebuild-listing-ranks', 600);
        $this->assertTrue($lock->get(), 'Test setup: the lock must be acquirable.');

        try {
            // Exit 0 (a concurrent rebuild is not a failure) but zero writes.
            $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
            $this->assertSame(0, DB::table('face_listing_ranks')->count());
        } finally {
            $lock->release();
        }
    }

    public function test_second_run_rotates_exposed_faces_behind_unexposed_ones(): void
    {
        // Single tier (free), uniform attributes: queue order is pure LRU.
        for ($i = 0; $i < 20; $i++) {
            $this->makeFace();
        }

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $firstOrder = $this->generationOrder(1);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $secondOrder = $this->generationOrder(2);

        // The 5 never-exposed Faces (ranks 16-20 of run 1) jump to the head;
        // the 15 exposed ones rotate to the back, keeping their id ASC order.
        $this->assertSame(
            array_merge(array_slice($firstOrder, 15), array_slice($firstOrder, 0, 15)),
            $secondOrder,
        );
    }

    public function test_rerun_is_idempotent_and_purges_generations_older_than_previous(): void
    {
        $this->makeFace();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // Keep N and N-1 only: generations 2 and 3 remain, 1 is purged.
        $this->assertSame(
            [2, 3],
            DB::table('face_listing_ranks')
                ->distinct()
                ->orderBy('generation')
                ->pluck('generation')
                ->map(fn ($g) => (int) $g)
                ->all(),
        );
    }

    public function test_fails_loud_and_writes_nothing_on_invalid_quota_config(): void
    {
        $this->makeFace();

        // Sum 101 must abort the rebuild before anything is written.
        config(['face_subscription_tiers.tiers.elite.capabilities.listing_quota' => 61]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(1);

        $this->assertSame(0, DB::table('face_listing_ranks')->count());
        $this->assertSame(0, Face::whereNotNull('last_page1_exposed_at')->count());
    }

    public function test_failed_run_preserves_the_previous_generation(): void
    {
        $this->makeFace();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        config(['face_subscription_tiers.tiers.free.capabilities.listing_quota' => 'five']);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(1);

        $this->assertSame(
            [1],
            DB::table('face_listing_ranks')
                ->distinct()
                ->pluck('generation')
                ->map(fn ($g) => (int) $g)
                ->all(),
            'The failed run must leave generation 1 untouched as the served generation.',
        );
    }

    public function test_runs_clean_on_an_empty_faces_table(): void
    {
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->assertSame(0, DB::table('face_listing_ranks')->count());
    }

    public function test_rebuild_listing_ranks_is_scheduled_daily_at_03_15_utc(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = collect($schedule->events());

        $event = $events->first(
            fn ($e) => str_contains($e->command ?? '', 'faces:rebuild-listing-ranks'),
        );

        $this->assertNotNull($event, 'RebuildFaceListingRanksCommand is not scheduled.');
        $this->assertSame('15 3 * * *', $event->expression, 'Schedule must be daily at 03:15 UTC.');
        $this->assertSame('UTC', $event->timezone, 'Schedule timezone must be UTC.');
        $this->assertTrue($event->withoutOverlapping, 'Schedule must have withoutOverlapping().');
        $this->assertTrue($event->onOneServer, 'Schedule must have onOneServer().');
    }
}
