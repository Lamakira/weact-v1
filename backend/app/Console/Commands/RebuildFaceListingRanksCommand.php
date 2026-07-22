<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Services\FaceEntitlementService;
use App\Services\FaceListingRankingService;
use App\Support\FaceListingRotation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildFaceListingRanksCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faces:rebuild-listing-ranks';

    /**
     * The console command description.
     */
    protected $description = 'Rebuild the materialized public-listing ranking of Faces (tier quotas via smoothed weighted round-robin, LRU page-1 fairness) as a new atomic generation.';

    /**
     * Rows per bulk INSERT into face_listing_ranks.
     */
    private const INSERT_CHUNK = 500;

    /**
     * Execute the console command.
     *
     * The rank ORDERS the listing, it never FILTERS it: eligibility
     * (active user) is re-checked live by the public controller, so a Face
     * deactivated after this run is simply a harmless hole in the ranking.
     *
     * Everything is written inside one transaction: the new generation only
     * becomes MAX(generation) at commit (atomic switch), and any failure
     * rolls back so the previous generation keeps being served.
     */
    public function handle(FaceListingRankingService $ranking, FaceEntitlementService $entitlements): int
    {
        // ONE writer of face_listing_ranks at a time — the SAME named lock as
        // faces:rotate-listing-ranks. The scheduler's withoutOverlapping()
        // only fences a command against itself, and the carousel tick runs
        // every five minutes: it lands on 03:00 and can still be running when
        // this rebuild starts at 03:15 (and a manual rebuild can race it at
        // any hour). Both compute MAX(generation)+1, so the loser would die on
        // the (generation, face_id) unique constraint — losing a whole night
        // of fairness if the loser is this command.
        $lock = Cache::lock(FaceListingRotation::WRITE_LOCK, FaceListingRotation::WRITE_LOCK_TTL_SECONDS);

        if (! $lock->get()) {
            $this->warn('Another writer already holds the face_listing_ranks lock — skipping.');

            return self::SUCCESS;
        }

        try {
            $weights = FaceListingRotation::tierWeightsByPriority($entitlements);
            $queues = $this->buildTierQueues($ranking, array_keys($weights));

            $sequence = $ranking->buildSequence($queues, $weights);
            // Snapshot of the per-tier queues, persisted alongside the
            // generation: it is the ONLY input of faces:rotate-listing-ranks,
            // which may not re-read Faces nor subscriptions.
            $tierPositions = $this->tierPositions($queues);

            [$generation, $rankedCount, $stampedCount] = DB::transaction(function () use ($sequence, $ranking, $tierPositions): array {
                // TOCTOU guard: a Face hard-deleted between queue building
                // (outside this transaction) and the insert below would
                // violate the face_id FK and abort the WHOLE nightly rebuild.
                // Drop vanished ids instead — ranks stay dense.
                $existing = DB::table('faces')->whereIn('id', $sequence)->pluck('id')
                    ->map(fn ($id) => (int) $id)->flip();
                $sequence = array_values(array_filter(
                    $sequence,
                    fn (int $faceId): bool => $existing->has($faceId),
                ));

                $generation = ((int) DB::table('face_listing_ranks')->max('generation')) + 1;
                // Single timestamp for the whole generation — read by the
                // faces:check-listing-ranks-freshness staleness watchdog.
                $generatedAt = now();

                $rows = [];
                foreach ($sequence as $index => $faceId) {
                    $rows[] = [
                        'generation' => $generation,
                        'face_id' => $faceId,
                        'rank' => $index + 1,
                        'created_at' => $generatedAt,
                        // A Face dropped by the TOCTOU guard above leaves a
                        // hole in its tier's `tier_rank` numbering — harmless:
                        // the tick only ORDERS BY tier_rank, it never assumes
                        // the queue is dense.
                        'tier' => $tierPositions[$faceId]['tier'] ?? null,
                        'tier_rank' => $tierPositions[$faceId]['tier_rank'] ?? null,
                        'source' => FaceListingRotation::SOURCE_NIGHTLY,
                    ];
                }

                foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                    DB::table('face_listing_ranks')->insert($chunk);
                }

                // LRU fairness state: the page-1 window (first PAGE_ONE_WINDOW
                // Faces) is about to be exposed — push them to the back of
                // their tier queue for the next rebuild. toBase(): this is
                // bookkeeping, it must NOT bump the user-facing updated_at.
                $pageOne = $ranking->pageOneWindow($sequence);
                if ($pageOne !== []) {
                    Face::query()
                        ->whereIn('id', $pageOne)
                        ->toBase()
                        ->update(['last_page1_exposed_at' => now()]);
                }

                // Purge. The new nightly base N is the served generation;
                // everything older is dead — including the tick generations
                // derived from the PREVIOUS base, which now pile up one per
                // rotation interval. So we keep N plus the latest previous
                // `nightly` generation (post-mortem of the last rebuild):
                // keeping "N-1" no longer means anything now that N-1 is
                // usually a tick. When no previous base is identifiable
                // (first run after the migration, or ranks seeded without a
                // `source`), the whole history goes.
                $previousNightly = DB::table('face_listing_ranks')
                    ->where('source', FaceListingRotation::SOURCE_NIGHTLY)
                    ->where('generation', '<', $generation)
                    ->max('generation');

                DB::table('face_listing_ranks')
                    ->where('generation', '<', $generation)
                    ->when(
                        $previousNightly !== null,
                        fn ($query) => $query->where('generation', '!=', (int) $previousNightly),
                    )
                    ->delete();

                return [$generation, count($sequence), count($pageOne)];
            });

            $this->info(sprintf(
                'Generation %d written: %d face(s) ranked, %d stamped as page-1 exposed.',
                $generation,
                $rankedCount,
                $stampedCount,
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Face listing rank rebuild failed — previous generation still served', [
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
            ]);
            $this->error("Rebuild failed: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Flatten the per-tier queues into a per-Face lookup of (tier, position in
     * its tier queue) — the two columns persisted alongside each rank row.
     *
     * They are what lets faces:rotate-listing-ranks rebuild the exact same
     * queues later WITHOUT re-reading `faces` or `face_subscriptions`: the
     * tick shifts each queue and re-interleaves, it never re-evaluates
     * eligibility or fairness.
     *
     * @param  array<string, list<int>>  $queues  tier => ordered face IDs
     * @return array<int, array{tier: string, tier_rank: int}>
     */
    private function tierPositions(array $queues): array
    {
        $positions = [];

        foreach ($queues as $tier => $faceIds) {
            foreach ($faceIds as $index => $faceId) {
                $positions[$faceId] = ['tier' => $tier, 'tier_rank' => $index + 1];
            }
        }

        return $positions;
    }

    /**
     * Load the eligible Faces (publiclyListable — the same shared scope the
     * public controller uses) and group them into ordered per-tier queues.
     *
     * A Face's tier is its `activeSubscription` plan's tier, Free otherwise —
     * the canonical plan→tier mapping, same as FaceEntitlementService. The
     * queues only need 4 scalar fields, so only those columns are hydrated
     * (the ofMany aggregation subquery is self-contained: the constrained
     * eager-load columns MUST be qualified but need not include
     * status/expires_at).
     *
     * @param  list<string>  $tiers
     * @return array<string, list<int>> tier => ordered face IDs
     */
    private function buildTierQueues(FaceListingRankingService $ranking, array $tiers): array
    {
        $grouped = array_fill_keys($tiers, []);

        // chunkById (id cursor), NOT offset chunk(): the live eligibility
        // predicate is re-evaluated per page, so concurrent user
        // deactivations would shift an OFFSET and silently skip Faces.
        Face::query()
            ->publiclyListable()
            ->select(['id', 'is_featured', 'profile_photo', 'last_page1_exposed_at'])
            ->with(['activeSubscription' => fn ($q) => $q->select(
                'face_subscriptions.id',
                'face_subscriptions.face_id',
                'face_subscriptions.plan',
            )])
            ->chunkById(1000, function ($faces) use (&$grouped): void {
                /** @var Face $face */
                foreach ($faces as $face) {
                    $tier = ($face->activeSubscription?->plan->tier() ?? FaceSubscriptionTier::Free)->value;

                    $grouped[$tier][] = [
                        'id' => (int) $face->id,
                        'is_featured' => (bool) $face->is_featured,
                        'has_photo' => $face->profile_photo !== null,
                        'last_page1_exposed_at' => $face->last_page1_exposed_at,
                    ];
                }
            });

        return array_map(
            fn (array $faces): array => $ranking->orderTierQueue($faces),
            $grouped,
        );
    }
}
