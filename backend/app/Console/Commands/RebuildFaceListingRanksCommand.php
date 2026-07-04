<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Services\FaceEntitlementService;
use App\Services\FaceListingRankingService;
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
        // The scheduler's withoutOverlapping() only fences cron runs; this
        // lock also covers a manual artisan run racing the nightly one (both
        // would read the same MAX(generation) and one would die mid-insert on
        // the (generation, face_id) unique constraint).
        $lock = Cache::lock('faces:rebuild-listing-ranks', 600);

        if (! $lock->get()) {
            $this->warn('Another rebuild already holds the lock — skipping.');

            return self::SUCCESS;
        }

        try {
            $weights = $this->tierWeightsByPriority($entitlements);
            $queues = $this->buildTierQueues($ranking, array_keys($weights));

            $sequence = $ranking->buildSequence($queues, $weights);

            [$generation, $rankedCount, $stampedCount] = DB::transaction(function () use ($sequence, $ranking): array {
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

                // Keep generations N and N-1 only. N-1 is never served once N
                // commits (readers always join MAX(generation)); it is kept
                // for post-mortem inspection of the last rotation.
                DB::table('face_listing_ranks')
                    ->where('generation', '<', $generation - 1)
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
     * listing_quota per tier, keyed by DESCENDING tier priority (elite first)
     * — the key order carries the WRR tie-break and the redistribution
     * priority, both driven by the configured `sort_priority`.
     *
     * The tier universe is the FaceSubscriptionTier enum, and each priority
     * comes from the central validated accessor (strict is_int guard lives
     * once, in FaceEntitlementService::buildCapabilities). Quotas are read
     * from the already-loaded config array — the ranking service fail-louds
     * on a missing or non-integer value.
     *
     * @return array<string, mixed> tier => listing_quota (validated by the service)
     */
    private function tierWeightsByPriority(FaceEntitlementService $entitlements): array
    {
        /** @var array<string, array<string, mixed>> $tiersConfig */
        $tiersConfig = config('face_subscription_tiers.tiers', []);

        $priorities = [];
        foreach (FaceSubscriptionTier::cases() as $tier) {
            $priorities[$tier->value] = $entitlements->capabilitiesForTier($tier)->sortPriority;
        }
        asort($priorities);

        $weights = [];
        foreach (array_keys($priorities) as $tierValue) {
            $weights[$tierValue] = ($tiersConfig[$tierValue]['capabilities'] ?? [])['listing_quota'] ?? null;
        }

        return $weights;
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
