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

            // Redistribution: an exhausted winner hands its slot to the queue
            // with the MOST Faces still waiting to be shown, ties broken by
            // tier priority.
            //
            // The v1 nightly ranking handed it to the highest-priority queue
            // with anything left, to pack page 1 with the most valuable Faces.
            // The carousel made that rule actively harmful, and production
            // proved it: with pro and starter empty, their 4+2 slots piled onto
            // elite, which owned 15 of the 16 page-1 slots for exactly 15
            // Faces. A tier that fills every slot it owns has no subset left to
            // rotate — the page froze, and the 56 % quota sold to elite was
            // silently over-delivered to 94 %.
            //
            // "Most Faces left" sends the surplus where it can actually be
            // rotated (the deep free queue in practice) instead of saturating a
            // small top tier. The priority tie-break keeps the commercial
            // invariant intact: at equal depth a paying tier still outranks a
            // free one. And since every queue is scanned, a slot nobody below
            // can absorb still finds the last queue holding Faces — a single
            // populated tier receives every slot, and the sequence never has
            // holes.
            $drawTier = $winner;
            if ($pointers[$drawTier] >= count($orderedQueues[$drawTier])) {
                $deepest = -1;
                foreach ($orderedQueues as $tier => $queue) {
                    $remaining = count($queue) - $pointers[$tier];
                    if ($remaining > $deepest) {
                        $deepest = $remaining;
                        $drawTier = $tier;
                    }
                }
            }

            $sequence[] = $orderedQueues[$drawTier][$pointers[$drawTier]];
            $pointers[$drawTier]++;
        }

        return $sequence;
    }

    /**
     * Order ONE tier queue according to the fairness rules:
     *
     *   1. `is_featured` Faces pinned first (admin boost);
     *   2. then Faces WITH a profile photo;
     *   3. then photo-less Faces relegated to the back;
     *
     * each block LRU-ordered by `last_page1_exposed_at` ASC with NULL (never
     * exposed — new-profile boost) first, final tiebreak `id` ASC everywhere
     * (strict total order).
     *
     * @param  list<array{id: int, is_featured: bool, has_photo: bool, last_page1_exposed_at: DateTimeInterface|null}>  $faces
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
     * @param  array{id: int, is_featured: bool, has_photo: bool, last_page1_exposed_at: DateTimeInterface|null}  $face
     * @return array{int, int, int}
     */
    private function queueSortKey(array $face): array
    {
        $block = match (true) {
            $face['is_featured'] => 0,
            $face['has_photo'] => 1,
            default => 2,
        };

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
