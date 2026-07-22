<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceListingRankingService;
use App\Support\FaceListingRotation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RotateFaceListingRanksCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeFace(array $attributes = []): Face
    {
        return Face::factory()->withActiveUser()->create($attributes);
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

    /**
     * @return list<int> distinct generations present, ascending
     */
    private function generations(): array
    {
        return DB::table('face_listing_ranks')
            ->distinct()
            ->orderBy('generation')
            ->pluck('generation')
            ->map(fn ($g) => (int) $g)
            ->all();
    }

    private function latestGeneration(): int
    {
        return (int) DB::table('face_listing_ranks')->max('generation');
    }

    /**
     * @return list<int> face IDs of one tier queue, tier_rank order
     */
    private function tierQueue(int $generation, string $tier): array
    {
        return DB::table('face_listing_ranks')
            ->where('generation', $generation)
            ->where('tier', $tier)
            ->orderBy('tier_rank')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Page 1 of the REAL public endpoint (default per_page = PAGE_ONE_WINDOW).
     *
     * @return list<string> public Face ids (uuid), in served order
     */
    private function publicPageOneIds(): array
    {
        $response = $this->getJson('/api/v1/public/faces');
        $response->assertOk();

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');

        return array_map(fn (array $row): string => (string) $row['id'], $data);
    }

    /**
     * Move the clock forward by whole rotation intervals (5 minutes each).
     *
     * Time MUST advance rather than the base being aged backwards: the tick
     * index of an already-written generation is recomputed from its own
     * `created_at` against the base, so shifting the base would carry every
     * past tick along with it and the idempotence guard would never release.
     */
    private function travelTicks(int $ticks): void
    {
        $this->travel($ticks * 5)->minutes();
    }

    /**
     * Seed a populated platform: 18 élite / 8 pro / 4 starter / 3 free.
     *
     * Every tier is deeper than the page-1 slots it owns (9/4/2/1), which is
     * what makes the carousel observable — a tier shorter than its slot count
     * cannot rotate. 33 Faces in total.
     */
    private function seedFourTiers(): void
    {
        foreach (range(1, 18) as $ignored) {
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 8) as $ignored) {
            FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 4) as $ignored) {
            FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 3) as $ignored) {
            $this->makeFace();
        }
    }

    /**
     * Seed the REAL production distribution (2026-07-22), scaled down on the
     * free tier only: 15 élite / 0 pro / 0 starter / 50 free.
     *
     * Free is the only tier whose depth is scaled (3137 -> 50): it is already
     * far deeper than the whole page-1 window, so its behaviour is identical
     * and the fixture stays cheap. Élite is kept at its EXACT production count
     * — 15 is the number the bug hangs on.
     */
    private function seedProductionDistribution(): void
    {
        foreach (range(1, 15) as $ignored) {
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 50) as $ignored) {
            $this->makeFace();
        }
    }

    public function test_a_saturated_top_tier_keeps_page_one_almost_still_by_design(): void
    {
        // FIDELITY: production 2026-07-22 — élite 15, pro 0, starter 0,
        // free 3137 (scaled to 50 here, see seedProductionDistribution()).
        //
        // Observed in production between the nightly generation 19 and the
        // tick generation 22 (3 ticks = 15 minutes): ONE single Face out of
        // the 16 of page 1 had changed — the one at rank 9, the only `free`
        // of the window.
        //
        // PO decision (2026-07-22), taken with this cost in full view: the
        // slots of the empty pro/starter tiers belong to élite, not to the
        // free pool — an Élite must not lose a page-1 place to a Découverte
        // because no Pro subscriber exists. Élite therefore holds 15 of the 16
        // slots for exactly 15 Faces, all permanently on screen, and only the
        // single free slot can bring in a new Face.
        //
        // So this test asserts the SHAPE of that accepted trade, not a fix:
        // exactly one Face renews per tick, and it is a free one. It turns any
        // future change of the redistribution rule into a deliberate
        // renegotiation instead of a silent one. It unfreezes on its own once
        // the élite tier grows past the slots it owns, or once pro/starter
        // subscribers take their own slots back.
        $this->seedProductionDistribution();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $baseGeneration = $this->latestGeneration();
        $window = FaceListingRankingService::PAGE_ONE_WINDOW;
        $basePageOne = array_slice($this->generationOrder($baseGeneration), 0, $window);

        $elites = DB::table('face_listing_ranks')
            ->where('generation', $baseGeneration)
            ->where('tier', 'elite')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // The premise of the trade: élite really does own 15 of the 16 slots.
        $this->assertCount(15, array_intersect($basePageOne, $elites));

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $tickPageOne = array_slice($this->generationOrder($this->latestGeneration()), 0, $window);
        $renewed = array_values(array_diff($tickPageOne, $basePageOne));

        $this->assertCount(1, $renewed, 'Only the single free slot can bring in a new Face.');
        $this->assertNotContains($renewed[0], $elites, 'The renewed Face comes from the free queue.');

        // The élite SET is frozen, but the degenerate-step guard keeps their
        // ORDER moving, so the page is not byte-identical from tick to tick.
        $this->assertNotSame($basePageOne, $tickPageOne);
    }

    public function test_a_tier_owning_as_many_slots_as_its_queue_is_deep_still_rotates(): void
    {
        // LATENT bug, independent of the cascade: rotateQueues() shifts a queue
        // by `(slots × T) mod size`. When the size DIVIDES the slot count the
        // shift is 0 for EVERY T — the queue is frozen forever, not just on one
        // unlucky tick. 9 élites for the 9 élite slots of a nominal page 1 is
        // enough to trigger it; production hit the same degeneracy with 15/15
        // after the cascade handed élite the empty tiers' slots.
        //
        // Hand-seeded base (no rebuild) so the slot count is EXACTLY 9 élite +
        // 7 free, i.e. the nominal 56/25/13/6 split with pro and starter empty,
        // without depending on how the cascade is fixed elsewhere.
        $elites = [];
        foreach (range(1, 9) as $ignored) {
            $elites[] = $this->makeFace()->id;
        }
        $frees = [];
        foreach (range(1, 20) as $ignored) {
            $frees[] = $this->makeFace()->id;
        }

        $rows = [];
        foreach ($elites as $index => $faceId) {
            $rows[] = [
                'generation' => 1, 'face_id' => $faceId, 'rank' => $index + 1,
                'created_at' => now(), 'tier' => 'elite', 'tier_rank' => $index + 1,
                'source' => 'nightly',
            ];
        }
        foreach ($frees as $index => $faceId) {
            $rows[] = [
                'generation' => 1, 'face_id' => $faceId, 'rank' => 10 + $index,
                'created_at' => now(), 'tier' => 'free', 'tier_rank' => $index + 1,
                'source' => 'nightly',
            ];
        }
        DB::table('face_listing_ranks')->insert($rows);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $tickGeneration = $this->latestGeneration();

        // Control: the free queue (7 slots for 20 Faces) does rotate, so a
        // failure below is about the degenerate shift and nothing else.
        $this->assertNotSame(
            $frees,
            $this->tierQueue($tickGeneration, 'free'),
            'Test setup: the free queue must rotate, otherwise the tick did not run.',
        );

        $this->assertNotSame(
            $elites,
            $this->tierQueue($tickGeneration, 'elite'),
            'A tier whose queue length divides its page-1 slot count gets a shift of '
            .'(9 × T) mod 9 = 0 for every T: its queue never moves again.',
        );
    }

    public function test_rebuild_persists_the_tier_snapshot_the_rotation_feeds_on(): void
    {
        $elite = $this->makeFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);
        $this->makeFace(); // free tier

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $rows = DB::table('face_listing_ranks')->orderBy('rank')->get();

        $this->assertSame(['nightly', 'nightly'], $rows->pluck('source')->all());
        $this->assertSame(
            ['elite', 'free'],
            $rows->pluck('tier')->all(),
            'Each rank row must carry the tier its Face was queued in.',
        );
        $this->assertSame(
            [1, 1],
            $rows->pluck('tier_rank')->map(fn ($r) => (int) $r)->all(),
            'tier_rank is the position INSIDE the tier queue, not the global rank.',
        );
    }

    public function test_rotation_renews_the_page_one_window_keeping_the_tier_composition(): void
    {
        $this->seedFourTiers();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $baseGeneration = $this->latestGeneration();
        $window = FaceListingRankingService::PAGE_ONE_WINDOW;
        $basePageOne = array_slice($this->generationOrder($baseGeneration), 0, $window);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $tickGeneration = $this->latestGeneration();
        $this->assertGreaterThan($baseGeneration, $tickGeneration);
        $this->assertSame(
            'tick',
            DB::table('face_listing_ranks')->where('generation', $tickGeneration)->value('source'),
        );

        $tickOrder = $this->generationOrder($tickGeneration);
        $tickPageOne = array_slice($tickOrder, 0, $window);

        // Same population, different window: the carousel turned.
        $this->assertNotSame($basePageOne, $tickPageOne);
        $this->assertCount(
            count($this->generationOrder($baseGeneration)),
            $tickOrder,
            'A tick is a permutation: it ranks exactly the Faces of its base.',
        );
        $this->assertEqualsCanonicalizing($this->generationOrder($baseGeneration), $tickOrder);

        // Composition preserved: 9 élite / 4 pro / 2 starter / 1 free.
        $tierByFace = DB::table('face_listing_ranks')
            ->where('generation', $baseGeneration)
            ->pluck('tier', 'face_id');
        $counts = ['elite' => 0, 'pro' => 0, 'starter' => 0, 'free' => 0];
        foreach ($tickPageOne as $faceId) {
            $counts[(string) $tierByFace[$faceId]]++;
        }
        $this->assertSame(['elite' => 9, 'pro' => 4, 'starter' => 2, 'free' => 1], $counts);
    }

    public function test_each_tier_queue_is_shifted_by_the_slots_that_tier_owns(): void
    {
        $this->seedFourTiers();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $baseGeneration = $this->latestGeneration();

        /** @var list<int> $eliteQueue élite queue of the base, tier_rank order */
        $eliteQueue = DB::table('face_listing_ranks')
            ->where('generation', $baseGeneration)
            ->where('tier', 'elite')
            ->orderBy('tier_rank')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->travelTicks(2); // T = 2 intervals of 5 minutes
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $rotatedElite = DB::table('face_listing_ranks')
            ->where('generation', $this->latestGeneration())
            ->where('tier', 'elite')
            ->orderBy('tier_rank')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 9 élite slots on page 1 × 2 ticks = 18, modulo the 18-deep queue = a
        // full turn: after two ticks the élite queue is back to its base order.
        $this->assertSame($eliteQueue, $rotatedElite);

        // Next interval: T = 3 → shift 27 mod 18 = 9, i.e. the queue cut in
        // half — the second half comes to the front.
        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $shiftedElite = DB::table('face_listing_ranks')
            ->where('generation', $this->latestGeneration())
            ->where('tier', 'elite')
            ->orderBy('tier_rank')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame(
            array_merge(array_slice($eliteQueue, 9), array_slice($eliteQueue, 0, 9)),
            $shiftedElite,
        );
    }

    public function test_disabled_rotation_writes_nothing(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->travelTicks(12);

        config(['face_listing_rotation.tick_minutes' => 0]);

        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1], $this->generations(), 'tick_minutes=0 must be a strict no-op.');
    }

    public function test_no_nightly_base_is_a_warning_not_a_failure(): void
    {
        $face = $this->makeFace();
        // Ranks seeded without a `source`: nothing is identifiable as the
        // fairness base, so there is nothing legitimate to permute.
        DB::table('face_listing_ranks')->insert([
            'generation' => 1,
            'face_id' => $face->id,
            'rank' => 1,
            'created_at' => now()->subHour(),
        ]);

        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1], $this->generations());
    }

    public function test_empty_ranks_table_is_a_warning_not_a_failure(): void
    {
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame(0, DB::table('face_listing_ranks')->count());
    }

    public function test_a_nightly_base_without_tier_snapshot_is_skipped(): void
    {
        $face = $this->makeFace();
        // A generation written before this feature: it has ranks and a source,
        // but no tier/tier_rank to rebuild the queues from.
        DB::table('face_listing_ranks')->insert([
            'generation' => 1,
            'face_id' => $face->id,
            'rank' => 1,
            'created_at' => now()->subHour(),
            'source' => 'nightly',
        ]);

        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1], $this->generations());
    }

    public function test_two_runs_inside_the_same_interval_write_one_generation(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->travel(7)->minutes(); // T = 1 (partial interval, still tick 1)

        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        $afterFirst = $this->generations();

        // Same interval (T is still 1): idempotent by tick index.
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame($afterFirst, $this->generations());
    }

    public function test_a_new_interval_writes_a_new_generation(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1, 2, 3], $this->generations());
    }

    public function test_rotation_stands_down_while_another_writer_holds_the_shared_lock(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->travelTicks(1);

        // ONE writer of face_listing_ranks at a time — held here on behalf of
        // whoever is writing (a concurrent rotation, or the nightly rebuild).
        $lock = Cache::lock(FaceListingRotation::WRITE_LOCK, FaceListingRotation::WRITE_LOCK_TTL_SECONDS);
        $this->assertTrue($lock->get(), 'Test setup: the lock must be acquirable.');

        try {
            $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
            $this->assertSame([1], $this->generations());
        } finally {
            $lock->release();
        }
    }

    public function test_the_nightly_rebuild_stands_down_while_a_rotation_holds_the_shared_lock(): void
    {
        // The other direction of the SAME lock, and the dangerous one: the tick
        // runs every five minutes, so it lands on 03:00 and can still be
        // running when the rebuild starts at 03:15. With one lock per command
        // both computed MAX(generation)+1 and the loser died on the
        // (generation, face_id) unique constraint — a whole night of fairness
        // lost whenever the loser was the rebuild.
        $this->seedFourTiers();

        $lock = Cache::lock(FaceListingRotation::WRITE_LOCK, FaceListingRotation::WRITE_LOCK_TTL_SECONDS);
        $this->assertTrue($lock->get(), 'Test setup: the lock must be acquirable.');

        try {
            $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
            $this->assertSame(
                0,
                DB::table('face_listing_ranks')->count(),
                'The rebuild must share the rotation lock, not hold one of its own.',
            );
        } finally {
            $lock->release();
        }
    }

    public function test_a_tick_inside_the_rebuild_interval_writes_nothing(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // Tick index 0: the rotation runs inside the very interval the base was
        // born in, so every shift is 0 and the generation it would write is a
        // character-for-character copy of the base — a full write and a wasted
        // slot in the retention window for zero visible change.
        $this->travel(2)->minutes();

        $this->artisan('faces:rotate-listing-ranks')
            ->expectsOutputToContain('nothing written')
            ->assertExitCode(0);

        $this->assertSame([1], $this->generations());

        // And it stays idempotent: the first real interval still rotates.
        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        $this->assertSame([1, 2], $this->generations());
    }

    public function test_purges_beyond_retained_ticks_but_never_the_nightly_base(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        config(['face_listing_rotation.retained_ticks' => 3]);

        // Six successive intervals → six tick generations (2..7).
        foreach (range(1, 6) as $tick) {
            $this->travelTicks(1);
            $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        }

        // Retention keeps the 3 most recent generations, and the base survives
        // no matter how far it drifts below the retention window — deleting it
        // would starve every future tick.
        $this->assertSame([1, 5, 6, 7], $this->generations());
        $this->assertSame(
            'nightly',
            DB::table('face_listing_ranks')->where('generation', 1)->value('source'),
        );
    }

    public function test_a_sparse_tier_still_yields_a_full_page_through_the_cascade(): void
    {
        // 3 élites for 9 élite slots: the shared buildSequence() must
        // redistribute the surplus to the next non-empty queue in priority
        // order — the reason the tick re-interleaves through the service
        // instead of slicing the queues itself.
        foreach (range(1, 3) as $ignored) {
            $face = $this->makeFace();
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
        }
        foreach (range(1, 20) as $ignored) {
            $this->makeFace();
        }

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $tickOrder = $this->generationOrder($this->latestGeneration());

        $this->assertCount(23, $tickOrder, 'Every listable Face keeps a rank.');
        $this->assertSame(
            range(1, 23),
            DB::table('face_listing_ranks')
                ->where('generation', $this->latestGeneration())
                ->orderBy('rank')
                ->pluck('rank')
                ->map(fn ($r) => (int) $r)
                ->all(),
            'Ranks stay dense 1..N — the cascade leaves no hole.',
        );

        $eliteIds = DB::table('face_listing_ranks')
            ->where('generation', $this->latestGeneration())
            ->where('tier', 'elite')
            ->pluck('face_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $window = array_slice($tickOrder, 0, FaceListingRankingService::PAGE_ONE_WINDOW);

        // All 3 élites stay on page 1: their queue is shorter than the slots
        // they own, so their shift collapses to 0 — they cannot rotate out.
        $this->assertCount(3, array_intersect($window, $eliteIds));
    }

    public function test_rotation_never_touches_the_lru_exposure_stamps(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $stampedBefore = Face::whereNotNull('last_page1_exposed_at')->pluck('id')->sort()->values()->all();
        $frozenUpdatedAt = '2026-01-01 00:00:00';
        DB::table('faces')->update(['updated_at' => $frozenUpdatedAt]);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        // The LRU fairness state belongs to the nightly rebuild ALONE: a tick
        // that stamped it would starve the Faces it briefly exposed.
        $this->assertSame(
            $stampedBefore,
            Face::whereNotNull('last_page1_exposed_at')->pluck('id')->sort()->values()->all(),
        );
        $this->assertSame(33, Face::where('updated_at', $frozenUpdatedAt)->count());
    }

    public function test_a_new_nightly_rebuild_resets_the_carousel_and_purges_the_ticks(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1, 2, 3], $this->generations());

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // Generations 2 and 3 were ticks of a base that no longer rules: only
        // the new base (4) and the previous nightly one (1, kept for
        // post-mortem) survive.
        $this->assertSame([1, 4], $this->generations());
        $this->assertSame(
            'nightly',
            DB::table('face_listing_ranks')->where('generation', 4)->value('source'),
        );
    }

    public function test_the_rotation_purge_never_deletes_a_nightly_generation(): void
    {
        $this->seedFourTiers();

        // TWO nightly generations: the current base (2) and the previous one
        // (1), which the rebuild deliberately keeps for post-mortem. A purge
        // driven by a numeric threshold alone drops that one within the hour —
        // deciding what a nightly generation is worth belongs to the rebuild.
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->assertSame([1, 2], $this->generations());

        config(['face_listing_rotation.retained_ticks' => 3]);

        foreach (range(1, 6) as $ignored) {
            $this->travelTicks(1);
            $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
        }

        // Ticks 3, 4 and 5 fell out of the retention window; both nightly
        // generations survived it.
        $this->assertSame([1, 2, 6, 7, 8], $this->generations());
        $this->assertSame(
            'nightly',
            DB::table('face_listing_ranks')->where('generation', 1)->value('source'),
        );
    }

    public function test_a_tier_owning_no_page_one_slot_still_rotates(): void
    {
        // Hand-seeded base where a NON-EMPTY tier owns ZERO page-1 slots: the
        // 16 first ranks are all élite, the whole starter queue sits beyond the
        // window. A shift of `slots × T` would be 0 forever for that queue, so
        // its Faces could never reach page 1 — the exact opposite of what a
        // carousel is for.
        $elites = [];
        foreach (range(1, 16) as $ignored) {
            $elites[] = $this->makeFace()->id;
        }
        $starters = [];
        foreach (range(1, 4) as $ignored) {
            $starters[] = $this->makeFace()->id;
        }

        $rows = [];
        foreach ($elites as $index => $faceId) {
            $rows[] = [
                'generation' => 1, 'face_id' => $faceId, 'rank' => $index + 1,
                'created_at' => now(), 'tier' => 'elite', 'tier_rank' => $index + 1,
                'source' => 'nightly',
            ];
        }
        foreach ($starters as $index => $faceId) {
            $rows[] = [
                'generation' => 1, 'face_id' => $faceId, 'rank' => 17 + $index,
                'created_at' => now(), 'tier' => 'starter', 'tier_rank' => $index + 1,
                'source' => 'nightly',
            ];
        }
        DB::table('face_listing_ranks')->insert($rows);

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        // Minimum step of one place per tick for a slot-less tier.
        $this->assertSame(
            [$starters[1], $starters[2], $starters[3], $starters[0]],
            $this->tierQueue($this->latestGeneration(), 'starter'),
        );
        // The élite queue is 16 deep for 16 slots, so its nominal step is a
        // WHOLE turn — which used to land it back on itself at every tick and
        // freeze it forever (the production symptom). The step degenerates to
        // one place per tick instead: showing all 16 either way, at least
        // their order keeps moving.
        $this->assertSame(
            [...array_slice($elites, 1), $elites[0]],
            $this->tierQueue($this->latestGeneration(), 'elite'),
        );

        // Three ticks in, the starter Face that was LAST in the base reaches
        // page 1 — the whole point of giving a slot-less tier a step.
        $this->travelTicks(2);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $window = array_slice(
            $this->generationOrder($this->latestGeneration()),
            0,
            FaceListingRankingService::PAGE_ONE_WINDOW,
        );
        $this->assertContains($starters[3], $window);
    }

    public function test_a_partial_tier_snapshot_is_skipped_rather_than_shrinking_the_listing(): void
    {
        // Half-stamped generation: readBaseQueues() ignores the rows without
        // tier/tier_rank, so rotating it would write a tick ranking FEWER
        // Faces than its base — the ignored ones would silently vanish from
        // the public listing until the next rebuild.
        $stamped = $this->makeFace();
        $unstamped = $this->makeFace();

        DB::table('face_listing_ranks')->insert([
            [
                'generation' => 1, 'face_id' => $stamped->id, 'rank' => 1,
                'created_at' => now(), 'tier' => 'free', 'tier_rank' => 1,
                'source' => 'nightly',
            ],
            [
                'generation' => 1, 'face_id' => $unstamped->id, 'rank' => 2,
                'created_at' => now(), 'tier' => null, 'tier_rank' => null,
                'source' => 'nightly',
            ],
        ]);

        Log::spy();

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1], $this->generations());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'PARTIAL tier snapshot'))
            ->once();
    }

    public function test_a_non_numeric_tick_minutes_is_denounced_not_silently_disabled(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
        $this->travelTicks(1);

        // A typo in the env file (FACE_LISTING_TICK_MINUTES=five) casts to 0,
        // i.e. it kills the carousel exactly like a deliberate kill switch —
        // and nothing anywhere would say so.
        config(['face_listing_rotation.tick_minutes' => 'five']);
        Log::spy();

        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1], $this->generations());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'invalid tick_minutes'))
            ->once();
    }

    public function test_a_tick_minutes_off_the_schedule_interval_is_denounced(): void
    {
        $this->seedFourTiers();
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // 7 minutes on a 5-minute schedule: whole tick indices are never
        // observed, so the page-1 window jumps by two steps instead of one.
        // Not fatal — but never silent.
        config(['face_listing_rotation.tick_minutes' => 7]);
        Log::spy();

        $this->travel(7)->minutes();
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        $this->assertSame([1, 2], $this->generations(), 'A misaligned interval still rotates.');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'not a multiple'))
            ->once();
    }

    public function test_the_migration_adopts_pre_existing_rows_as_the_nightly_base(): void
    {
        // Between `migrate` and the first rebuild, every row in production is a
        // generation written by the nightly rebuild — the only writer of the
        // table until now. Left with source = NULL they would be invisible as
        // a nightly base: the freshness watchdog would fire Log::critical +
        // FAILURE every hour and the filtered listing would lose its base.
        $face = $this->makeFace();
        DB::table('face_listing_ranks')->insert([
            'generation' => 1,
            'face_id' => $face->id,
            'rank' => 1,
            'created_at' => now()->subHour(),
            'source' => null,
        ]);

        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require database_path(
            'migrations/2026_07_22_000000_add_rotation_columns_to_face_listing_ranks_table.php',
        );
        // Re-running up() on a schema that already carries the columns must be
        // the data backfill and nothing else (guarded by Schema::hasColumn).
        $migration->up();

        $this->assertSame(
            'nightly',
            DB::table('face_listing_ranks')->where('generation', 1)->value('source'),
        );
        $this->assertSame(1, FaceListingRotation::latestNightlyGeneration());
    }

    public function test_a_new_elite_subscriber_reaches_page_one_within_one_tick(): void
    {
        // AC3, end to end: a Face that subscribes Élite AFTER a nightly
        // rebuild must be on page 1 no later than one tick after the next one.
        //
        // 11 élites already exposed on page 1 by previous rebuilds. The four
        // tiers are all populated on purpose: an empty tier cascades its slots
        // to élite, which would put every élite on page 1 and make this test
        // prove nothing.
        foreach (range(1, 11) as $offset) {
            $face = $this->makeFace(['last_page1_exposed_at' => now()->subDays(2)->addMinutes($offset)]);
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);
        }
        foreach (range(1, 8) as $ignored) {
            FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 4) as $ignored) {
            FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->makeFace()->id]);
        }
        foreach (range(1, 3) as $ignored) {
            $this->makeFace();
        }

        // The newcomer: an existing Face, never exposed on page 1
        // (last_page1_exposed_at NULL), that just subscribed Élite.
        $newcomer = $this->makeFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $newcomer->id]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        // The "never exposed" boost (NULL LRU stamp = head of its tier queue)
        // is what puts her among the 9 élite slots of the window: without it
        // she would be 12th of 12 élites and this assertion would fail.
        $this->assertContains(
            $newcomer->uuid,
            $this->publicPageOneIds(),
            'A fresh Élite subscriber must reach page 1 at the very next nightly rebuild.',
        );

        $this->travelTicks(1);
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);

        // And one tick later she is still there: the carousel renews the
        // window, it does not evict a newcomer within the tick that follows.
        $this->assertContains(
            $newcomer->uuid,
            $this->publicPageOneIds(),
            'A fresh Élite subscriber must still be on page 1 one tick later.',
        );
    }

    public function test_rotate_listing_ranks_is_scheduled_every_five_minutes(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())->first(
            fn ($e) => str_contains($e->command ?? '', 'faces:rotate-listing-ranks'),
        );

        $this->assertNotNull($event, 'RotateFaceListingRanksCommand is not scheduled.');
        $this->assertSame('*/5 * * * *', $event->expression, 'Rotation must run every 5 minutes.');
        $this->assertTrue($event->withoutOverlapping, 'Schedule must have withoutOverlapping().');
        $this->assertTrue($event->onOneServer, 'Schedule must have onOneServer().');
    }
}
