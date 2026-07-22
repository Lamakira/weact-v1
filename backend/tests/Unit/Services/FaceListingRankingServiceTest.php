<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FaceListingRankingService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The ranking service is pure (no I/O): these tests exercise the smoothed
 * WRR sequence, the redistribution rule, the tier-queue ordering and the
 * fail-loud quota validation without touching the database.
 */
class FaceListingRankingServiceTest extends TestCase
{
    private FaceListingRankingService $service;

    /**
     * Nominal quotas, keyed by descending tier priority (elite first) as the
     * rebuild command passes them. Mirror of config/face_subscription_tiers
     * (56/25/13/6 → the standard 16-slot page splits 9/4/2/1).
     *
     * @var array<string, int>
     */
    private array $weights = [
        'elite' => 56,
        'pro' => 25,
        'starter' => 13,
        'free' => 6,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaceListingRankingService;
    }

    /**
     * @param  array<string, int>  $sizes  tier => queue length; IDs are tier-namespaced (elite 1000+, pro 2000+, ...)
     * @return array<string, list<int>>
     */
    private function makeQueues(array $sizes): array
    {
        $bases = ['elite' => 1000, 'pro' => 2000, 'starter' => 3000, 'free' => 4000];

        $queues = [];
        foreach ($sizes as $tier => $size) {
            $queues[$tier] = array_map(
                fn (int $i): int => $bases[$tier] + $i,
                $size > 0 ? range(1, $size) : []
            );
        }

        return $queues;
    }

    /**
     * @param  list<int>  $prefix
     * @return array<string, int> tier => count, based on the ID namespaces of makeQueues()
     */
    private function countByTier(array $prefix): array
    {
        $counts = ['elite' => 0, 'pro' => 0, 'starter' => 0, 'free' => 0];
        foreach ($prefix as $id) {
            $tier = match (intdiv($id, 1000)) {
                1 => 'elite',
                2 => 'pro',
                3 => 'starter',
                default => 'free',
            };
            $counts[$tier]++;
        }

        return $counts;
    }

    public function test_every_prefix_approximates_the_tier_quotas(): void
    {
        // Queues deep enough that no tier is exhausted within 30 slots.
        $queues = $this->makeQueues(['elite' => 40, 'pro' => 20, 'starter' => 10, 'free' => 10]);

        $sequence = $this->service->buildSequence($queues, $this->weights);

        // Smoothed WRR guarantee: for any prefix length k, each tier holds
        // between floor(k*q/100) and ceil(k*q/100) slots (±1 tolerance) —
        // the property the controller relies on for any per_page 1-30.
        foreach ([1, 5, 10, 15, 20, 30] as $k) {
            $counts = $this->countByTier(array_slice($sequence, 0, $k));

            foreach ($this->weights as $tier => $quota) {
                $expected = $k * $quota / 100;
                $this->assertGreaterThanOrEqual(
                    floor($expected) - 1,
                    $counts[$tier],
                    "Tier {$tier} underfilled in prefix {$k}: got {$counts[$tier]} for quota {$quota}%."
                );
                $this->assertLessThanOrEqual(
                    ceil($expected) + 1,
                    $counts[$tier],
                    "Tier {$tier} overfilled in prefix {$k}: got {$counts[$tier]} for quota {$quota}%."
                );
            }
        }
    }

    public function test_page_one_window_holds_the_expected_quota_split(): void
    {
        $queues = $this->makeQueues(['elite' => 20, 'pro' => 10, 'starter' => 5, 'free' => 5]);

        $sequence = $this->service->buildSequence($queues, $this->weights);
        $counts = $this->countByTier($this->service->pageOneWindow($sequence));

        // Deterministic smoothed-WRR outcome for 56/25/13/6 over 16 slots:
        // 9 élite, 4 pro, 2 starter, 1 free — the PO-calibrated page-1 mix
        // (Starter must visibly outrank Free: 2 slots vs 1, and its first
        // slot comes at position 4 vs position 9 for free).
        $this->assertSame(['elite' => 9, 'pro' => 4, 'starter' => 2, 'free' => 1], $counts);
    }

    public function test_the_cascade_never_pushes_a_tier_past_its_own_page_one_quota(): void
    {
        // Production distribution, 2026-07-22: élite 15, pro 0, starter 0,
        // free 3137 (only free is scaled down here — it stays far deeper than
        // the window, so the outcome is identical).
        //
        // Pro and starter being empty, their 4 + 2 page-1 slots cascade to the
        // highest-priority non-empty queue, i.e. élite, which ends up holding
        // 15 of the 16 slots — three times its 56 % quota. Free, which has
        // 3137 members, gets ONE.
        //
        // Two consequences, both unacceptable:
        //  - commercial: the whole page 1 is the same 15 subscribers forever,
        //    there is nothing left to sell to a Face who wants exposure;
        //  - mechanical: 15 slots for a 15-deep queue freezes the carousel
        //    (see RotateFaceListingRanksCommandTest).
        //
        // A tier's quota is a CEILING on page 1 as long as another non-empty
        // queue can absorb the surplus. The cascade must still exist for the
        // deep tail (nothing else to draw from), it must not overrun the
        // window while free has thousands of Faces waiting.
        $queues = $this->makeQueues(['elite' => 15, 'pro' => 0, 'starter' => 0, 'free' => 50]);

        $counts = $this->countByTier($this->service->pageOneWindow(
            $this->service->buildSequence($queues, $this->weights)
        ));

        $this->assertLessThanOrEqual(
            9,
            $counts['elite'],
            "Élite owns 9 of the 16 page-1 slots (quota 56 %) but took {$counts['elite']}: "
            .'the slots of the empty tiers cascaded past its own quota.',
        );
        // Same statement, seen from the tier that is starved: everything élite
        // does not own goes to the only other populated tier.
        $this->assertGreaterThanOrEqual(
            7,
            $counts['free'],
            "Free is the only other populated tier and got {$counts['free']} of 16 slots.",
        );
    }

    public function test_sequence_emits_every_face_exactly_once(): void
    {
        $queues = $this->makeQueues(['elite' => 7, 'pro' => 13, 'starter' => 3, 'free' => 9]);

        $sequence = $this->service->buildSequence($queues, $this->weights);

        $all = array_merge(...array_values($queues));
        $this->assertCount(count($all), $sequence);
        $this->assertSame([], array_diff($all, $sequence), 'Every queued face must appear in the sequence.');
        $this->assertSame($sequence, array_values(array_unique($sequence)), 'No face may appear twice.');
    }

    public function test_within_a_tier_the_queue_order_is_preserved(): void
    {
        $queues = $this->makeQueues(['elite' => 10, 'pro' => 10, 'starter' => 5, 'free' => 5]);

        $sequence = $this->service->buildSequence($queues, $this->weights);

        foreach ($queues as $tier => $queue) {
            $emitted = array_values(array_intersect($sequence, $queue));
            $this->assertSame($queue, $emitted, "Tier {$tier} must be drawn in queue order (fairness).");
        }
    }

    public function test_ties_on_current_are_broken_by_tier_priority(): void
    {
        // Two tiers at 50/50: slot 1 is a 50-50 tie — the FIRST key (highest
        // priority) must win, giving a strict A B A B alternation.
        $queues = ['a' => [1, 3, 5, 7], 'b' => [2, 4, 6, 8]];
        $weights = ['a' => 50, 'b' => 50];

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $this->service->buildSequence($queues, $weights));
    }

    public function test_slots_of_an_empty_tier_go_to_the_queue_with_the_most_faces_left(): void
    {
        // Redistribution targets DEPTH (Faces still waiting), not rank, with
        // priority only breaking ties. On shallow queues like these the two
        // rules agree — pro is both the deepest and the highest-priority
        // non-empty queue — which is precisely the point: the rewrite changes
        // the outcome ONLY where a saturated top tier faces a deep one (see
        // test_a_saturated_top_tier_never_swallows_more_than_its_own_quota).
        $queues = $this->makeQueues(['elite' => 0, 'pro' => 2, 'starter' => 1, 'free' => 1]);

        $sequence = $this->service->buildSequence($queues, $this->weights);

        // Slot 1 (elite wins, empty) -> pro, the deepest queue (2 left).
        // Slot 2 (pro wins) -> pro.
        // Slot 3 (elite wins, empty; pro drained) -> starter, tie 1-1 with
        //   free broken by priority — a paying tier stays ahead of free.
        // Slot 4 (starter wins, drained) -> free, the only queue left.
        $this->assertSame([2001, 2002, 3001, 4001], $sequence);
    }

    public function test_a_saturated_top_tier_never_swallows_more_than_its_own_quota(): void
    {
        // The production shape that froze the carousel: pro and starter empty,
        // a small elite tier, a deep free tier. Elite owns 9 of the 16 page-1
        // slots (quota 56) and must not receive the 4+2 orphan slots of the
        // empty tiers — free has 3000+ Faces to rotate through them.
        $queues = $this->makeQueues(['elite' => 15, 'pro' => 0, 'starter' => 0, 'free' => 60]);

        $counts = $this->countByTier($this->service->pageOneWindow(
            $this->service->buildSequence($queues, $this->weights)
        ));

        $this->assertSame(9, $counts['elite']);
        $this->assertSame(7, $counts['free']);
    }

    public function test_single_populated_tier_receives_all_slots_in_queue_order(): void
    {
        $queues = $this->makeQueues(['elite' => 0, 'pro' => 0, 'starter' => 0, 'free' => 4]);

        $sequence = $this->service->buildSequence($queues, $this->weights);

        $this->assertSame([4001, 4002, 4003, 4004], $sequence);
    }

    public function test_order_tier_queue_pins_featured_faces_first(): void
    {
        $now = new DateTimeImmutable('2026-07-01 00:00:00');

        $queue = $this->service->orderTierQueue([
            ['id' => 1, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => null],
            ['id' => 2, 'is_featured' => true, 'has_photo' => false, 'last_page1_exposed_at' => $now],
            ['id' => 3, 'is_featured' => true, 'has_photo' => true, 'last_page1_exposed_at' => null],
        ]);

        // Featured first (never-exposed #3 before exposed #2), photo-less
        // status irrelevant inside the featured block.
        $this->assertSame([3, 2, 1], $queue);
    }

    public function test_order_tier_queue_relegates_photo_less_faces_to_the_back(): void
    {
        $queue = $this->service->orderTierQueue([
            ['id' => 1, 'is_featured' => false, 'has_photo' => false, 'last_page1_exposed_at' => null],
            ['id' => 2, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => null],
            ['id' => 3, 'is_featured' => false, 'has_photo' => false, 'last_page1_exposed_at' => null],
            ['id' => 4, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => null],
        ]);

        // Photo block first (id ASC), then the photo-less block (id ASC).
        $this->assertSame([2, 4, 1, 3], $queue);
    }

    public function test_order_tier_queue_rotates_exposed_faces_behind_unexposed_ones(): void
    {
        $makeFace = fn (int $id, ?DateTimeImmutable $exposedAt): array => [
            'id' => $id,
            'is_featured' => false,
            'has_photo' => true,
            'last_page1_exposed_at' => $exposedAt,
        ];

        // Round 1: nobody exposed yet -> pure id ASC.
        $faces = [$makeFace(1, null), $makeFace(2, null), $makeFace(3, null)];
        $this->assertSame([1, 2, 3], $this->service->orderTierQueue($faces));

        // Faces 1 and 2 get exposed on page 1 (face 1 earlier than face 2).
        $faces = [
            $makeFace(1, new DateTimeImmutable('2026-07-01 03:15:00')),
            $makeFace(2, new DateTimeImmutable('2026-07-02 03:15:00')),
            $makeFace(3, null),
        ];

        // Round 2: never-exposed #3 jumps to the head, then LRU (oldest
        // exposure first) — the exposed rotate to the back of the queue.
        $this->assertSame([3, 1, 2], $this->service->orderTierQueue($faces));
    }

    public function test_order_tier_queue_breaks_all_ties_by_id_asc(): void
    {
        $exposedAt = new DateTimeImmutable('2026-07-01 03:15:00');

        $queue = $this->service->orderTierQueue([
            ['id' => 9, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => $exposedAt],
            ['id' => 4, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => $exposedAt],
            ['id' => 7, 'is_featured' => false, 'has_photo' => true, 'last_page1_exposed_at' => $exposedAt],
        ]);

        $this->assertSame([4, 7, 9], $queue);
    }

    public function test_page_one_window_returns_the_first_sixteen_ids(): void
    {
        $sequence = range(101, 130);

        $this->assertSame(range(101, 116), $this->service->pageOneWindow($sequence));
        $this->assertSame([101, 102], $this->service->pageOneWindow([101, 102]));
    }

    public function test_throws_when_quotas_do_not_sum_to_100(): void
    {
        $queues = $this->makeQueues(['elite' => 1, 'pro' => 1, 'starter' => 1, 'free' => 1]);
        $badWeights = ['elite' => 60, 'pro' => 25, 'starter' => 10, 'free' => 6];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must sum to 100, got 101');

        $this->service->buildSequence($queues, $badWeights);
    }

    public function test_throws_on_missing_or_non_integer_quota(): void
    {
        $queues = $this->makeQueues(['elite' => 1, 'pro' => 1, 'starter' => 1, 'free' => 1]);
        // A fractional quota must fail loud, never be silently truncated.
        $badWeights = ['elite' => 60, 'pro' => 25.5, 'starter' => 10, 'free' => 5];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Missing or non-integer listing_quota for tier 'pro'");

        $this->service->buildSequence($queues, $badWeights);
    }

    public function test_throws_on_negative_quota_even_when_the_sum_is_100(): void
    {
        $queues = $this->makeQueues(['elite' => 1, 'pro' => 1, 'starter' => 1, 'free' => 1]);
        // Sums to 100, but the negative tier would never win a WRR slot.
        $badWeights = ['elite' => 120, 'pro' => 25, 'starter' => 10, 'free' => -55];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Negative listing_quota (-55) for tier 'free'");

        $this->service->buildSequence($queues, $badWeights);
    }

    public function test_throws_when_a_queue_tier_has_no_weight(): void
    {
        $queues = ['elite' => [1], 'vip' => [2]];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Queue tier(s) without a listing_quota weight: vip');

        $this->service->buildSequence($queues, ['elite' => 100]);
    }

    public function test_empty_queues_produce_an_empty_sequence(): void
    {
        $queues = $this->makeQueues(['elite' => 0, 'pro' => 0, 'starter' => 0, 'free' => 0]);

        $this->assertSame([], $this->service->buildSequence($queues, $this->weights));
    }
}
