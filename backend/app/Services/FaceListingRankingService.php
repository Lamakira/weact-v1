<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use RuntimeException;

/**
 * Pure (no I/O) ranking engine for the public Faces listing rotation.
 *
 * Turns per-tier ordered queues of face IDs into ONE global sequence via a
 * smoothed weighted round-robin (nginx algorithm): every prefix of the
 * sequence approximates the configured tier quotas — for any page size
 * (per_page 1-30) — as long as every tier still has Faces left. Once a
 * queue is exhausted its slots cascade to the highest-priority remaining
 * queue, so the deep tail degrades into per-tier blocks by design.
 *
 * The service never touches the database, config, or clock: callers (the
 * rebuild command) load the Faces, resolve the quotas and build the queues,
 * then persist the returned sequence as a new ranking generation.
 */
class FaceListingRankingService
{
    /**
     * Size of the "page 1" exposure window — MUST equal what the real public
     * page 1 displays: 16 = the public listing's default per_page, itself
     * chosen for full grid rows (4×4 desktop, commit 55895ef9). The rebuild
     * command stamps `last_page1_exposed_at` on the first PAGE_ONE_WINDOW
     * Faces of each new generation.
     */
    public const PAGE_ONE_WINDOW = 16;

    /**
     * The quotas are percentages: they must sum to exactly this total, and
     * each smoothed-WRR winner pays this amount back after taking a slot.
     */
    private const TOTAL_WEIGHT = 100;

    /**
     * Interleave the per-tier queues into one global ranked sequence.
     *
     * Smoothed WRR: on every slot each tier does `current += weight`; the
     * highest `current` wins the slot and pays back `current -= 100`. Ties on
     * `current` are broken by tier priority — the order of the `$weights`
     * keys, which callers MUST pass sorted by descending tier priority
     * (highest `sort_priority` rank, i.e. elite, first).
     *
     * A slot won by a tier whose queue is exhausted is redistributed to the
     * first non-empty queue in that same priority order, so a sparse tier
     * never leaves holes in the sequence.
     *
     * @param  array<string, list<int>>  $queues  tier => face IDs, each queue already ordered (see orderTierQueue())
     * @param  array<string, mixed>  $weights  tier => listing quota; integers summing to 100 (fail-loud otherwise)
     * @return list<int> face IDs, best rank first
     *
     * @throws RuntimeException on invalid quotas or on a queue/weight tier mismatch
     */
    public function buildSequence(array $queues, array $weights): array
    {
        $validated = $this->validateWeights($weights);

        $missing = array_diff_key($queues, $validated);
        if ($missing !== []) {
            throw new RuntimeException(
                'Queue tier(s) without a listing_quota weight: '.implode(', ', array_keys($missing)).'.'
            );
        }

        /** @var array<string, list<int>> $orderedQueues keyed in priority order, missing tiers = empty queue */
        $orderedQueues = [];
        foreach (array_keys($validated) as $tier) {
            $orderedQueues[$tier] = $queues[$tier] ?? [];
        }

        $current = array_fill_keys(array_keys($validated), 0);
        $pointers = array_fill_keys(array_keys($validated), 0);
        $totalSlots = array_sum(array_map('count', $orderedQueues));

        $sequence = [];

        for ($slot = 0; $slot < $totalSlots; $slot++) {
            // Smoothed WRR step: everyone accumulates, the max wins. Strict
            // `>` keeps the FIRST (= highest-priority) tier on ties.
            $winner = null;
            $best = null;
            foreach ($validated as $tier => $weight) {
                $current[$tier] += $weight;
                if ($best === null || $current[$tier] > $best) {
                    $best = $current[$tier];
                    $winner = $tier;
                }
            }

            /** @var string $winner always set: $validated is never empty here */
            $current[$winner] -= self::TOTAL_WEIGHT;

            // Redistribution: an exhausted winner hands its slot to the first
            // non-empty queue in priority order — UPWARD, so the surplus of an
            // empty tier benefits the paying tiers above it.
            //
            // PO decision (2026-07-22), taken with its cost in full view: an
            // Élite must not lose a page-1 slot to a Découverte just because no
            // Pro subscriber exists. The slots of the empty tiers belong to the
            // tier that paid the most, not to the free pool.
            //
            // The accepted consequence is that the carousel FREEZES whenever
            // the top tier has no more Faces than the slots it ends up owning:
            // 15 Élites absorbing 15 of the 16 page-1 slots are all on screen
            // permanently, leaving no subset to rotate. Only the single
            // Découverte slot renews. This resolves itself as the tier grows
            // past its slot count, or as soon as Pro/Starter subscribers take
            // their own slots back. Do NOT "fix" this by sending the surplus
            // downward without asking — that trade was made deliberately.
            $drawTier = $winner;
            if ($pointers[$drawTier] >= count($orderedQueues[$drawTier])) {
                foreach ($orderedQueues as $tier => $queue) {
                    if ($pointers[$tier] < count($queue)) {
                        $drawTier = $tier;
                        break;
                    }
                }
            }

            $sequence[] = $orderedQueues[$drawTier][$pointers[$drawTier]];
            $pointers[$drawTier]++;
        }

        return $sequence;
    }

    /**
     * Take the effective exclusivities out of the queue they sit in.
     *
     * Only ids actually present in the queues are taken: an exclusivity
     * absent from them (Face created after the nightly generation, or not
     * publicly listable) has no rank to lift and waits for the next night.
     * The block is ordered by id ascending — the same canonical order for the
     * nightly rebuild and for every tick — and capped at $limit.
     *
     * @param  array<string, list<int>>  $queues  tier => ordered face IDs
     * @param  list<int>  $exclusiveIds
     * @return array{list<int>, array<string, list<int>>} the exclusive block, the queues without it
     */
    public function separateExclusives(array $queues, array $exclusiveIds, int $limit): array
    {
        $present = [];
        foreach ($queues as $faceIds) {
            foreach ($faceIds as $faceId) {
                $present[$faceId] = true;
            }
        }

        $block = array_values(array_filter(
            array_unique($exclusiveIds),
            fn (int $faceId): bool => isset($present[$faceId]),
        ));
        sort($block);
        $block = array_slice($block, 0, max(0, $limit));

        if ($block === []) {
            return [[], $queues];
        }

        $taken = array_flip($block);
        $rest = [];
        foreach ($queues as $tier => $faceIds) {
            $rest[$tier] = array_values(array_filter(
                $faceIds,
                fn (int $faceId): bool => ! isset($taken[$faceId]),
            ));
        }

        return [$block, $rest];
    }

    /**
     * Put the exclusive block at the head of the top tier's queue. The
     * persisted tier snapshot is derived from these queues, so an exclusivity
     * is written with that tier even when its nightly tier was lower (Face
     * promoted during the day).
     *
     * @param  array<string, list<int>>  $queues  tier => ordered face IDs, exclusives already removed
     * @param  list<int>  $block
     * @return array<string, list<int>>
     */
    public function placeExclusivesAtHead(array $queues, array $block, string $topTier): array
    {
        if ($block === []) {
            return $queues;
        }

        $queues[$topTier] = [...$block, ...($queues[$topTier] ?? [])];

        return $queues;
    }

    /**
     * Lift the exclusive block to ranks 1..k of an interleaved sequence.
     *
     * Heading the top tier's queue, the exclusives were already drawn within
     * the page-1 window: lifting them only reorders page 1, it never changes
     * which Faces it holds nor the per-tier slot counts.
     *
     * @param  list<int>  $sequence
     * @param  list<int>  $block
     * @return list<int>
     */
    public function liftExclusives(array $sequence, array $block): array
    {
        if ($block === []) {
            return $sequence;
        }

        $taken = array_flip($block);

        return [...$block, ...array_values(array_filter(
            $sequence,
            fn (int $faceId): bool => ! isset($taken[$faceId]),
        ))];
    }

    /**
     * Order ONE tier queue according to the fairness rules:
     *
     *   1. Faces WITH a profile photo first;
     *   2. then photo-less Faces relegated to the back;
     *
     * each block LRU-ordered by `last_page1_exposed_at` ASC with NULL (never
     * exposed — new-profile boost) first, final tiebreak `id` ASC everywhere
     * (strict total order).
     *
     * @param  list<array{id: int, has_photo: bool, last_page1_exposed_at: DateTimeInterface|null}>  $faces
     * @return list<int> ordered face IDs
     */
    public function orderTierQueue(array $faces): array
    {
        usort($faces, function (array $a, array $b): int {
            return $this->queueSortKey($a) <=> $this->queueSortKey($b);
        });

        return array_column($faces, 'id');
    }

    /**
     * The first PAGE_ONE_WINDOW face IDs of a sequence — the "page 1" window
     * whose members get their `last_page1_exposed_at` stamped by the rebuild.
     *
     * @param  list<int>  $sequence
     * @return list<int>
     */
    public function pageOneWindow(array $sequence): array
    {
        return array_slice($sequence, 0, self::PAGE_ONE_WINDOW);
    }

    /**
     * Comparable sort key implementing the tier-queue total order.
     *
     * @param  array{id: int, has_photo: bool, last_page1_exposed_at: DateTimeInterface|null}  $face
     * @return array{int, int, int}
     */
    private function queueSortKey(array $face): array
    {
        $block = $face['has_photo'] ? 0 : 1;

        // NULL exposure = never on page 1 = head of its block.
        $exposedAt = $face['last_page1_exposed_at']?->getTimestamp() ?? PHP_INT_MIN;

        return [$block, $exposedAt, $face['id']];
    }

    /**
     * Fail-loud quota validation (same philosophy as the historic
     * sort_priority guard): a broken config/face_subscription_tiers.php must
     * surface as an exception, never silently mis-weight the rotation.
     *
     * @param  array<string, mixed>  $weights
     * @return array<string, int>
     *
     * @throws RuntimeException when a quota is missing/non-integer or the sum is not 100
     */
    private function validateWeights(array $weights): array
    {
        if ($weights === []) {
            throw new RuntimeException(
                'No listing_quota weights given — check config/face_subscription_tiers.php.'
            );
        }

        $validated = [];
        foreach ($weights as $tier => $quota) {
            if (! is_int($quota)) {
                throw new RuntimeException(
                    "Missing or non-integer listing_quota for tier '{$tier}' in "
                    .'config/face_subscription_tiers.php — run `php artisan config:clear`.'
                );
            }
            // A negative weight can still sum to 100 with an inflated sibling
            // but silently degenerates the WRR (the tier never wins a slot).
            if ($quota < 0) {
                throw new RuntimeException(
                    "Negative listing_quota ({$quota}) for tier '{$tier}' in "
                    .'config/face_subscription_tiers.php.'
                );
            }
            $validated[$tier] = $quota;
        }

        $sum = array_sum($validated);
        if ($sum !== self::TOTAL_WEIGHT) {
            throw new RuntimeException(
                "listing_quota values in config/face_subscription_tiers.php must sum to 100, got {$sum}."
            );
        }

        return $validated;
    }
}
