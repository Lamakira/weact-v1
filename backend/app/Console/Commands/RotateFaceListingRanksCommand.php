<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FaceEntitlementService;
use App\Services\FaceListingRankingService;
use App\Support\FaceListingRotation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RotateFaceListingRanksCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faces:rotate-listing-ranks';

    /**
     * The console command description.
     */
    protected $description = 'Rotate the public-listing carousel: shift each tier queue of the current nightly generation by the slots that tier owns on page 1, re-interleave, and write it as a new tick generation.';

    /**
     * Rows per bulk INSERT into face_listing_ranks (same batching as the
     * nightly rebuild — a tick writes exactly as many rows).
     */
    private const INSERT_CHUNK = 500;

    /**
     * Execute the console command.
     *
     * This command is deliberately POOR: it re-reads NOTHING about the Faces
     * themselves — no publiclyListable(), no `faces` query, no
     * `face_subscriptions` query. It only permutes rows already written by
     * faces:rebuild-listing-ranks, using the `tier` / `tier_rank` snapshot
     * that rebuild persisted. All the fairness (LRU page-1 exposure, featured
     * boost, photo-less relegation, new-profile boost) stays in the nightly
     * rebuild, which keeps its schedule and its staleness watchdog.
     *
     * Idempotence is carried by the TICK INDEX — the number of complete
     * intervals elapsed since the nightly base was born. Two runs inside the
     * same interval write one generation, so a manual run, a retried cron and
     * a scheduler hiccup are all harmless.
     */
    public function handle(FaceListingRankingService $ranking, FaceEntitlementService $entitlements): int
    {
        $tickMinutes = FaceListingRotation::tickMinutes();

        // Audited BEFORE the kill switch: a non-numeric setting casts to 0,
        // i.e. it disables the carousel, and that must never happen silently.
        $this->auditConfiguration($tickMinutes);

        // Kill switch: a single env var + `config:clear` disables the whole
        // carousel and the page falls back EXACTLY on the nightly order — no
        // redeploy, no migration, no code path left half-applied.
        if ($tickMinutes <= 0) {
            $this->info('Rotation disabled (face_listing_rotation.tick_minutes <= 0) — nothing written.');

            return self::SUCCESS;
        }

        // ONE writer of face_listing_ranks at a time — the SAME named lock as
        // faces:rebuild-listing-ranks. The scheduler's withoutOverlapping()
        // only fences a command against itself; the two commands both compute
        // MAX(generation)+1 and the loser would die on the (generation,
        // face_id) unique constraint — and if the loser were the nightly
        // rebuild, a whole night of fairness would be lost.
        $lock = Cache::lock(FaceListingRotation::WRITE_LOCK, FaceListingRotation::WRITE_LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another writer already holds the face_listing_ranks lock — skipping.');

            return self::SUCCESS;
        }

        try {
            $base = $this->currentNightlyBase();

            if ($base === null) {
                // Not an incident on its own (fresh install, or ranks written
                // before the `source` column existed): the listing keeps
                // serving MAX(generation). The nightly staleness watchdog is
                // the component in charge of screaming about a dead rebuild.
                Log::warning('Face listing rotation skipped — no nightly generation to rotate', [
                    'action' => 'run `php artisan faces:rebuild-listing-ranks` once, then the rotation resumes on its own',
                ]);
                $this->warn('No nightly generation to rotate — skipping.');

                return self::SUCCESS;
            }

            [$baseGeneration, $baseCreatedAt] = $base;

            $tickIndex = $this->tickIndex($baseCreatedAt, now(), $tickMinutes);

            if ($tickIndex === 0) {
                // The tick fell inside the very interval the rebuild was born
                // in: every shift is 0, so the generation we would write is
                // character-for-character the nightly base. Copying it would
                // cost a full write and push a useless generation into the
                // retention window for zero visible change.
                $this->info("Tick 0 of generation {$baseGeneration} — the nightly order is already the current window, nothing written.");

                return self::SUCCESS;
            }

            if ($this->alreadyRotatedAt($baseGeneration, $baseCreatedAt, $tickMinutes, $tickIndex)) {
                $this->info("Tick {$tickIndex} already written for generation {$baseGeneration} — nothing to do.");

                return self::SUCCESS;
            }

            [$queues, $slots] = $this->readBaseQueues($baseGeneration);

            if ($queues === []) {
                // A nightly generation predating this feature: it has ranks
                // but no tier snapshot, so there is nothing to permute. The
                // next rebuild backfills it.
                Log::warning('Face listing rotation skipped — the nightly generation carries no tier snapshot', [
                    'generation' => $baseGeneration,
                    'action' => 'run `php artisan faces:rebuild-listing-ranks` once to write the tier/tier_rank columns',
                ]);
                $this->warn("Generation {$baseGeneration} has no tier snapshot — skipping.");

                return self::SUCCESS;
            }

            // PARTIAL snapshot: readBaseQueues() skips the rows without
            // tier/tier_rank, so a half-stamped generation would silently
            // produce a tick RANKING FEWER FACES than its base — the skipped
            // ones would vanish from the public listing until the next
            // rebuild. A tick is a permutation or it is nothing.
            $queuedCount = array_sum(array_map('count', $queues));
            $baseRowCount = (int) DB::table('face_listing_ranks')
                ->where('generation', $baseGeneration)
                ->count();

            if ($queuedCount !== $baseRowCount) {
                Log::warning('Face listing rotation skipped — the nightly generation carries a PARTIAL tier snapshot', [
                    'generation' => $baseGeneration,
                    'rows_with_tier_snapshot' => $queuedCount,
                    'rows_in_generation' => $baseRowCount,
                    'action' => 'run `php artisan faces:rebuild-listing-ranks` once to rewrite a complete generation',
                ]);
                $this->warn("Generation {$baseGeneration} has a partial tier snapshot ({$queuedCount}/{$baseRowCount}) — skipping.");

                return self::SUCCESS;
            }

            $rotatedQueues = $this->rotateQueues($queues, $slots, $tickIndex);

            // Re-interleaving goes through the UNCHANGED nightly engine: the
            // redistribution of an empty tier's slots to the highest-priority
            // remaining queue is what keeps sparse tiers from leaving holes,
            // and reimplementing it here would fork that behaviour.
            $sequence = $ranking->buildSequence(
                $rotatedQueues,
                FaceListingRotation::tierWeightsByPriority($entitlements),
            );

            [$generation, $rankedCount] = DB::transaction(
                fn (): array => $this->writeTickGeneration($baseGeneration, $rotatedQueues, $sequence),
            );

            $this->info(sprintf(
                'Tick %d of generation %d written as generation %d: %d face(s) ranked.',
                $tickIndex,
                $baseGeneration,
                $generation,
                $rankedCount,
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Nothing is half-written (single transaction) and the previous
            // generation keeps being served. The most plausible failure is a
            // Face hard-deleted between the base read and the insert (FK
            // violation): NOT guarded against on purpose — guarding it would
            // mean querying `faces`, which this command must never do. The
            // NEXT TICK self-heals on its own: face_listing_ranks.face_id is
            // declared cascadeOnDelete, so deleting the Face already removed
            // its rows from the base generation — no rebuild needed.
            Log::error('Face listing rotation failed — previous generation still served', [
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
            ]);
            $this->error("Rotation failed: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Denounce a configuration that would misbehave in SILENCE.
     *
     * Two traps, both of which leave the command exiting SUCCESS:
     *
     *  1. a non-numeric `tick_minutes` (typo in the env file,
     *     `FACE_LISTING_TICK_MINUTES=five`) casts to 0, i.e. it kills the
     *     carousel exactly like a deliberate kill switch would;
     *  2. a `tick_minutes` that is not a multiple of the scheduler interval
     *     makes whole tick indices unreachable — with 7 minutes on a
     *     5-minute schedule, some intervals are simply never observed and the
     *     page-1 window jumps by two steps instead of one.
     */
    private function auditConfiguration(int $tickMinutes): void
    {
        $raw = config('face_listing_rotation.tick_minutes', FaceListingRotation::SCHEDULE_INTERVAL_MINUTES);

        if (! is_numeric($raw)) {
            Log::warning('Face listing rotation is DISABLED by an invalid tick_minutes — the carousel is frozen on the nightly order', [
                'configured_value' => is_scalar($raw) ? (string) $raw : gettype($raw),
                'action' => 'set FACE_LISTING_TICK_MINUTES to an integer (5 = default, 0 = deliberate kill switch) then run `php artisan config:clear`',
            ]);
            $this->warn('Invalid face_listing_rotation.tick_minutes — treated as 0 (rotation disabled).');

            return;
        }

        if ($tickMinutes > 0 && $tickMinutes % FaceListingRotation::SCHEDULE_INTERVAL_MINUTES !== 0) {
            Log::warning('face_listing_rotation.tick_minutes is not a multiple of the rotation schedule interval — tick indices will be skipped', [
                'tick_minutes' => $tickMinutes,
                'schedule_interval_minutes' => FaceListingRotation::SCHEDULE_INTERVAL_MINUTES,
                'action' => 'use a multiple of the schedule interval, or change the schedule in routes/console.php',
            ]);
            $this->warn(sprintf(
                'face_listing_rotation.tick_minutes (%d) is not a multiple of the %d-minute schedule — some ticks will be skipped.',
                $tickMinutes,
                FaceListingRotation::SCHEDULE_INTERVAL_MINUTES,
            ));
        }
    }

    /**
     * The nightly generation the carousel rotates around: its number and its
     * birth timestamp (the origin of the tick clock).
     *
     * @return array{int, Carbon}|null
     */
    private function currentNightlyBase(): ?array
    {
        $generation = FaceListingRotation::latestNightlyGeneration();

        if ($generation === null) {
            return null;
        }

        // MIN(created_at): the rebuild stamps one single timestamp on the
        // whole generation, min() just picks it without assuming a row order.
        $createdAt = DB::table('face_listing_ranks')
            ->where('generation', $generation)
            ->min('created_at');

        if ($createdAt === null) {
            return null;
        }

        return [$generation, Carbon::parse($createdAt)];
    }

    /**
     * Number of COMPLETE rotation intervals between the nightly base and a
     * given instant. Clamped at 0 so a clock stepping backwards degrades into
     * "no rotation yet" instead of shifting the queues by a negative amount.
     */
    private function tickIndex(Carbon $baseCreatedAt, Carbon $at, int $tickMinutes): int
    {
        $elapsedMinutes = max(0.0, (float) $baseCreatedAt->diffInMinutes($at));

        return (int) floor($elapsedMinutes / $tickMinutes);
    }

    /**
     * Whether the current interval already has its generation.
     *
     * There is no "tick index" column: the index is recomputed from the last
     * tick's own `created_at` against the same base and the same interval
     * length, which is exactly the value it was written with. `>=` (not `===`)
     * so a backwards clock jump cannot make the command rewrite past ticks.
     */
    private function alreadyRotatedAt(int $baseGeneration, Carbon $baseCreatedAt, int $tickMinutes, int $tickIndex): bool
    {
        $lastTick = DB::table('face_listing_ranks')
            ->where('source', FaceListingRotation::SOURCE_TICK)
            ->where('generation', '>', $baseGeneration)
            ->orderByDesc('generation')
            ->first(['created_at']);

        if ($lastTick === null) {
            return false;
        }

        return $this->tickIndex($baseCreatedAt, Carbon::parse($lastTick->created_at), $tickMinutes) >= $tickIndex;
    }

    /**
     * Rebuild the per-tier queues of the nightly base from its persisted
     * snapshot, plus the number of page-1 slots each tier owns.
     *
     * The slot count is READ from the base window (ranks 1..PAGE_ONE_WINDOW)
     * rather than recomputed from the quotas: it is by construction the
     * WRR split of the real page 1 (9/4/2/1 for 56/25/13/6), AND it already
     * accounts for the slots an empty tier cascaded to its neighbours. Shifting
     * a queue by exactly the slots its tier owns is what makes one tick renew
     * the whole page-1 window without reshuffling the tier composition.
     *
     * @return array{array<string, list<int>>, array<string, int>} tier => face IDs ordered by tier_rank, tier => page-1 slots
     */
    private function readBaseQueues(int $baseGeneration): array
    {
        $rows = DB::table('face_listing_ranks')
            ->where('generation', $baseGeneration)
            ->whereNotNull('tier')
            ->whereNotNull('tier_rank')
            ->orderBy('tier')
            ->orderBy('tier_rank')
            ->get(['face_id', 'tier', 'rank']);

        /** @var array<string, list<int>> $queues */
        $queues = [];
        /** @var array<string, int> $slots */
        $slots = [];

        foreach ($rows as $row) {
            $tier = (string) $row->tier;

            $queues[$tier][] = (int) $row->face_id;

            if ((int) $row->rank <= FaceListingRankingService::PAGE_ONE_WINDOW) {
                $slots[$tier] = ($slots[$tier] ?? 0) + 1;
            }
        }

        return [$queues, $slots];
    }

    /**
     * Shift every tier queue by `page-1 slots × tick index`, modulo its own
     * length — the carousel itself.
     *
     * Anchoring the shift on the tick INDEX (and not on "one shift per run")
     * is what makes the command idempotent: rerunning it inside the same
     * interval, or after a missed run, always lands on the same permutation.
     *
     * @param  array<string, list<int>>  $queues
     * @param  array<string, int>  $slots
     * @return array<string, list<int>>
     */
    private function rotateQueues(array $queues, array $slots, int $tickIndex): array
    {
        $rotated = [];

        foreach ($queues as $tier => $faceIds) {
            $size = count($faceIds);
            // Step of one tick for this tier. max(1, …): a NON-EMPTY tier
            // whose members all sit beyond the page-1 window owns 0 slots
            // there, and a step of 0 would freeze its queue FOREVER — its
            // Faces could never reach page 1, which is the exact opposite of
            // what the carousel is for. It advances by one place per tick.
            $step = max(1, $slots[$tier] ?? 0);

            // Degenerate step: when the queue length DIVIDES the step, every
            // tick shifts by a whole number of turns and lands back exactly
            // where it started — the queue is frozen forever, not just for one
            // tick. The obvious case is a tier owning as many slots as it has
            // Faces (9 Faces, 9 slots), which is how the production listing
            // froze. Such a tier does show all its Faces whatever we do, so no
            // shift can change WHICH ones are visible — but leaving the step at
            // a full turn also freezes their ORDER, which reads as a dead page.
            // Falling back to one place per tick keeps it visibly alive.
            if ($size > 1 && $step % $size === 0) {
                $step = 1;
            }

            $shift = $size === 0 ? 0 : ($step * $tickIndex) % $size;

            $rotated[$tier] = $shift === 0
                ? $faceIds
                : array_merge(array_slice($faceIds, $shift), array_slice($faceIds, 0, $shift));
        }

        return $rotated;
    }

    /**
     * Persist the permuted sequence as a new `tick` generation.
     *
     * Runs inside one transaction: the new generation only becomes
     * MAX(generation) at commit (atomic switch), exactly like the nightly
     * rebuild — readers never see a half-written carousel.
     *
     * @param  array<string, list<int>>  $rotatedQueues
     * @param  list<int>  $sequence
     * @return array{int, int} written generation, ranked Face count
     */
    private function writeTickGeneration(int $baseGeneration, array $rotatedQueues, array $sequence): array
    {
        $generation = ((int) DB::table('face_listing_ranks')->max('generation')) + 1;
        $generatedAt = now();

        // The tick's own queue positions, so a tick generation describes the
        // listing it actually serves. It is never read back as a base: the
        // base lookup filters on source = 'nightly'.
        $positions = [];
        foreach ($rotatedQueues as $tier => $faceIds) {
            foreach ($faceIds as $index => $faceId) {
                $positions[$faceId] = ['tier' => $tier, 'tier_rank' => $index + 1];
            }
        }

        $rows = [];
        foreach ($sequence as $index => $faceId) {
            $rows[] = [
                'generation' => $generation,
                'face_id' => $faceId,
                'rank' => $index + 1,
                'created_at' => $generatedAt,
                'tier' => $positions[$faceId]['tier'] ?? null,
                'tier_rank' => $positions[$faceId]['tier_rank'] ?? null,
                'source' => FaceListingRotation::SOURCE_TICK,
            ];
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table('face_listing_ranks')->insert($chunk);
        }

        // Retention. Tick generations only exist to keep a visitor's pinned
        // pagination consistent, so keeping the last `retained_ticks` of them
        // (the one just written INCLUDED) is enough.
        //
        // The rotation NEVER deletes a `nightly` row, whatever the numeric
        // threshold says. Two of them are protected here: the base it rotates
        // around — deleting it would starve every future tick — and the
        // PREVIOUS nightly generation, which the rebuild deliberately keeps
        // for post-mortem and which a purely numeric threshold would drop
        // within the hour. Deciding what a nightly generation is worth belongs
        // to the rebuild alone.
        $retainedTicks = max(1, (int) config('face_listing_rotation.retained_ticks', 12));

        DB::table('face_listing_ranks')
            ->where('generation', '<', $generation - $retainedTicks + 1)
            ->where('generation', '!=', $baseGeneration)
            ->where(function ($query): void {
                // `source <> 'nightly'` alone would spare the legacy NULL rows
                // (SQL three-valued logic), which are purgeable history.
                $query->whereNull('source')
                    ->orWhere('source', '!=', FaceListingRotation::SOURCE_NIGHTLY);
            })
            ->delete();

        return [$generation, count($sequence)];
    }
}
