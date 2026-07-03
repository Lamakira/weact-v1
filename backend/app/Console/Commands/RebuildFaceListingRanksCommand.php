<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Face;
use App\Services\FaceListingRankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
    public function handle(FaceListingRankingService $ranking): int
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
            $weights = $this->tierWeightsByPriority();
            $queues = $this->buildTierQueues($ranking, array_keys($weights));

            $sequence = $ranking->buildSequence($queues, $weights);

            $generation = DB::transaction(function () use (&$sequence, $ranking): int {
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

                $rows = [];
                foreach ($sequence as $index => $faceId) {
                    $rows[] = [
                        'generation' => $generation,
                        'face_id' => $faceId,
                        'rank' => $index + 1,
                    ];
                }

                foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                    DB::table('face_listing_ranks')->insert($chunk);
                }

                // LRU fairness state: the first 15 Faces are about to be
                // exposed on page 1, push them to the back of their tier
                // queue for the next rebuild. toBase(): this is bookkeeping,
                // it must NOT bump the Faces' user-facing updated_at.
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

                return $generation;
            });

            $this->info(sprintf(
                'Generation %d written: %d face(s) ranked, %d stamped as page-1 exposed.',
                $generation,
                count($sequence),
                count($ranking->pageOneWindow($sequence)),
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
     * @return array<string, mixed> tier => listing_quota (validated by the service)
     */
    private function tierWeightsByPriority(): array
    {
        /** @var array<string, array<string, mixed>> $tiersConfig */
        $tiersConfig = config('face_subscription_tiers.tiers', []);

        $tiers = array_keys($tiersConfig);
        usort($tiers, fn (string $a, string $b): int => $this->tierSortPriority($a) <=> $this->tierSortPriority($b));

        $weights = [];
        foreach ($tiers as $tier) {
            $weights[$tier] = config("face_subscription_tiers.tiers.{$tier}.capabilities.listing_quota");
        }

        return $weights;
    }

    /**
     * Load the eligible Faces (active user, same live gate as the public
     * controller) and group them into ordered per-tier queues.
     *
     * A Face's tier is its `activeSubscription` plan, `free` otherwise.
     *
     * @param  list<string>  $tiers
     * @return array<string, list<int>> tier => ordered face IDs
     */
    private function buildTierQueues(FaceListingRankingService $ranking, array $tiers): array
    {
        $grouped = array_fill_keys($tiers, []);

        // chunkById (id cursor), NOT offset chunk(): the live whereHas
        // predicate is re-evaluated per page, so concurrent user
        // deactivations would shift an OFFSET and silently skip Faces.
        Face::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->with('activeSubscription')
            ->chunkById(1000, function ($faces) use (&$grouped): void {
                /** @var Face $face */
                foreach ($faces as $face) {
                    $tier = $face->activeSubscription?->plan->value ?? 'free';

                    if (! array_key_exists($tier, $grouped)) {
                        throw new RuntimeException(
                            "Face #{$face->id} resolves to unknown tier '{$tier}' — "
                            .'check config/face_subscription_tiers.php.'
                        );
                    }

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

    /**
     * Fail-loud sort_priority resolution (same guard as the historic public
     * controller ordering): a missing or non-integer priority must surface,
     * never silently mis-order the redistribution.
     */
    private function tierSortPriority(string $tier): int
    {
        $priority = config("face_subscription_tiers.tiers.{$tier}.capabilities.sort_priority");

        if (! is_int($priority)) {
            throw new RuntimeException(
                "Missing or non-integer sort_priority for tier '{$tier}' in "
                .'config/face_subscription_tiers.php — run `php artisan config:clear`.'
            );
        }

        return $priority;
    }
}
