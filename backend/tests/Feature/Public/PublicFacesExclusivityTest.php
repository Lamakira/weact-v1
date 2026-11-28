<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exclusivité Élite (#245) : places 1 à k de la page 1 du listing public,
 * observées par l'API après le calcul de nuit et les passages du carrousel.
 */
class PublicFacesExclusivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Face uuids by tier, in creation order.
     *
     * @var array<string, list<string>>
     */
    private array $tiers = ['elite' => [], 'pro' => [], 'starter' => [], 'free' => []];

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeFace(string $tier, array $attributes = []): Face
    {
        $face = Face::factory()->withActiveUser()->create($attributes);

        if ($tier !== 'free') {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
        }

        $this->tiers[$tier][] = $face->uuid;

        return $face;
    }

    /**
     * Production shape (2026-11-28): 20 Élite, 7 Pro, 6 Starter and a free
     * tier far deeper than the page-1 window (scaled down from ~4 090).
     *
     * @return list<Face> the Élites flagged exclusive, in creation order
     */
    private function seedListing(int $exclusives, int $elite = 20, int $pro = 7, int $starter = 6, int $free = 40): array
    {
        $flagged = [];

        // The LAST Élites created are flagged: without the exclusivity they
        // would sit at the tail of the Élite queue (id order), off page 1.
        // for, not range(1, $n): range(1, 0) yields [1, 0], so an "empty"
        // tier would silently get two Faces.
        for ($i = 1; $i <= $elite; $i++) {
            $isExclusive = $i > $elite - $exclusives;
            $face = $this->makeFace('elite', ['is_exclusive' => $isExclusive]);
            if ($isExclusive) {
                $flagged[] = $face;
            }
        }
        for ($i = 0; $i < $pro; $i++) {
            $this->makeFace('pro');
        }
        for ($i = 0; $i < $starter; $i++) {
            $this->makeFace('starter');
        }
        for ($i = 0; $i < $free; $i++) {
            $this->makeFace('free');
        }

        return $flagged;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<string> public Face ids (uuid), in served order
     */
    private function page(int $page = 1, array $query = []): array
    {
        $response = $this->getJson('/api/v1/public/faces?'.http_build_query(['page' => $page] + $query));
        $response->assertOk();

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');

        return array_map(fn (array $row): string => (string) $row['id'], $data);
    }

    /**
     * @param  list<string>  $uuids
     * @return list<string> the tier of each uuid, in order
     */
    private function tiersOf(array $uuids): array
    {
        $lookup = [];
        foreach ($this->tiers as $tier => $members) {
            foreach ($members as $uuid) {
                $lookup[$uuid] = $tier;
            }
        }

        return array_map(fn (string $uuid): string => $lookup[$uuid] ?? 'unknown', $uuids);
    }

    /**
     * @param  list<Face>  $faces
     * @return list<string>
     */
    private function uuids(array $faces): array
    {
        return array_map(fn (Face $face): string => $face->uuid, $faces);
    }

    private function nightly(): void
    {
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);
    }

    private function tick(): void
    {
        $this->travel(5)->minutes();
        $this->artisan('faces:rotate-listing-ranks')->assertExitCode(0);
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     */
    private function assertSameSet(array $expected, array $actual, string $message = ''): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, $message);
    }

    public function test_page_one_shows_four_exclusives_then_five_elites_four_pro_two_starter_one_discovery(): void
    {
        $exclusives = $this->uuids($this->seedListing(4));

        $this->nightly();

        foreach (range(0, 3) as $pass) {
            if ($pass > 0) {
                $this->tick();
            }

            $page = $this->page();

            $this->assertCount(16, $page);
            $this->assertSameSet($exclusives, array_slice($page, 0, 4), "Pass {$pass}: places 1 to 4 are the exclusives.");
            $this->assertSame(
                [
                    'elite', 'elite', 'elite', 'elite', 'elite',
                    'pro', 'pro', 'pro', 'pro',
                    'starter', 'starter',
                    'free',
                ],
                $this->tiersOf(array_slice($page, 4)),
                "Pass {$pass}: then 5 Élites, 4 Pro, 2 Starter, 1 Découverte.",
            );
            $this->assertEmpty(array_intersect($exclusives, array_slice($page, 4)));
        }
    }

    public function test_exclusives_never_appear_beyond_page_one(): void
    {
        $exclusives = $this->uuids($this->seedListing(4));

        $this->nightly();

        foreach (range(0, 5) as $pass) {
            if ($pass > 0) {
                $this->tick();
            }

            // 73 Faces = 5 pages of 16.
            foreach (range(2, 5) as $pageNumber) {
                $this->assertEmpty(
                    array_intersect($exclusives, $this->page($pageNumber)),
                    "Pass {$pass}: no exclusive on page {$pageNumber}.",
                );
            }
        }
    }

    public function test_exclusive_order_changes_every_pass_and_each_takes_place_one_within_k_passes(): void
    {
        $exclusives = $this->uuids($this->seedListing(4));

        $this->nightly();

        $previous = array_slice($this->page(), 0, 4);
        $firstPlaces = [];

        foreach (range(1, 4) as $pass) {
            $this->tick();
            $block = array_slice($this->page(), 0, 4);

            $this->assertSameSet($exclusives, $block);
            $this->assertNotSame($previous, $block, "Pass {$pass}: the exclusive order must change.");

            $firstPlaces[] = $block[0];
            $previous = $block;
        }

        $this->assertSameSet($exclusives, $firstPlaces, 'Within 4 passes, each exclusive takes place 1 once.');
    }

    public function test_non_exclusive_elites_rotate_on_the_remaining_slots_without_duplicate_or_omission(): void
    {
        $exclusives = $this->uuids($this->seedListing(4));
        $rotating = array_values(array_diff($this->tiers['elite'], $exclusives));

        $this->nightly();

        $seenOnPageOne = [];

        foreach (range(0, 3) as $pass) {
            if ($pass > 0) {
                $this->tick();
            }

            $response = $this->getJson('/api/v1/public/faces');
            $generation = (int) $response->json('meta.generation');

            // Within one generation: every Face exactly once across the pages,
            // Élites first on every page.
            $all = [];
            foreach (range(1, 5) as $pageNumber) {
                $page = $this->page($pageNumber, ['generation' => $generation]);
                $all = [...$all, ...$page];

                if ($pageNumber > 1) {
                    $tiers = $this->tiersOf($page);
                    $eliteCount = count(array_filter($tiers, fn (string $t): bool => $t === 'elite'));
                    $this->assertSame(array_fill(0, $eliteCount, 'elite'), array_slice($tiers, 0, $eliteCount));
                }
            }
            $this->assertCount(73, $all);
            $this->assertCount(73, array_unique($all), "Pass {$pass}: no duplicate across the pages.");

            $pageOneElites = array_slice($this->page(1, ['generation' => $generation]), 4, 5);
            $this->assertEmpty(array_diff($pageOneElites, $rotating));
            $seenOnPageOne = [...$seenOnPageOne, ...$pageOneElites];
        }

        // 16 rotating Élites, 5 slots per pass: 4 passes show all of them.
        $this->assertSameSet($rotating, array_values(array_unique($seenOnPageOne)));
    }

    public function test_with_two_exclusives_page_one_keeps_nine_elite_slots(): void
    {
        $exclusives = $this->uuids($this->seedListing(2));

        $this->nightly();

        foreach (range(0, 2) as $pass) {
            if ($pass > 0) {
                $this->tick();
            }

            $page = $this->page();

            $this->assertSameSet($exclusives, array_slice($page, 0, 2));
            $this->assertSame(
                ['elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'pro', 'pro', 'pro', 'pro', 'starter', 'starter', 'free'],
                $this->tiersOf($page),
            );
        }
    }

    public function test_without_exclusives_page_one_keeps_nine_elite_slots(): void
    {
        $this->seedListing(0);

        $this->nightly();
        $this->tick();

        $this->assertSame(
            ['elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'elite', 'pro', 'pro', 'pro', 'pro', 'starter', 'starter', 'free'],
            $this->tiersOf($this->page()),
        );
    }

    public function test_an_exclusive_flag_on_a_face_that_is_not_elite_is_ignored(): void
    {
        $this->seedListing(0);
        // Flag left on a Pro (the hourly job has not dropped it yet).
        $pro = Face::query()->where('uuid', $this->tiers['pro'][0])->firstOrFail();
        $pro->update(['is_exclusive' => true]);

        $this->nightly();
        $page = $this->page();
        $this->assertSame('elite', $this->tiersOf([$page[0]])[0], 'Nightly: an Élite still opens the page.');
        $this->assertNotContains($pro->uuid, array_slice($page, 0, 9));

        $this->tick();
        $page = $this->page();
        $this->assertSame('elite', $this->tiersOf([$page[0]])[0], 'Tick: an Élite still opens the page.');
        $this->assertNotContains($pro->uuid, array_slice($page, 0, 9));
    }

    public function test_an_exclusive_that_loses_elite_during_the_day_leaves_places_one_to_k_at_the_next_pass(): void
    {
        $flagged = $this->seedListing(4);

        $this->nightly();
        $this->assertSameSet($this->uuids($flagged), array_slice($this->page(), 0, 4));

        // Subscription cancelled after the nightly computation, flag not yet
        // dropped by the hourly job.
        $lost = $flagged[0];
        FaceSubscription::query()->where('face_id', $lost->id)->update([
            'status' => FaceSubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->tick();

        $page = $this->page();
        $this->assertSameSet($this->uuids(array_slice($flagged, 1)), array_slice($page, 0, 3));
        $this->assertNotContains($lost->uuid, array_slice($page, 0, 3));
    }

    public function test_an_exclusive_granted_after_the_nightly_computation_to_a_face_that_became_elite_reaches_places_one_to_k_at_the_next_pass(): void
    {
        $flagged = $this->seedListing(3);

        $this->nightly();

        // A Découverte at night, far from page 1: it becomes Élite and is
        // flagged during the day. Its nightly tier is still `free`.
        $promoted = Face::query()->where('uuid', $this->tiers['free'][30])->firstOrFail();
        $this->assertNotContains($promoted->uuid, $this->page());
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $promoted->id]);
        $promoted->update(['is_exclusive' => true]);

        $this->tick();

        $page = $this->page();
        $this->assertSameSet(
            [...$this->uuids($flagged), $promoted->uuid],
            array_slice($page, 0, 4),
            'The day-promoted exclusive sits among places 1 to 4, not at the end of the page.',
        );
        $this->assertSame(
            ['pro', 'pro', 'pro', 'pro', 'starter', 'starter', 'free'],
            $this->tiersOf(array_slice($page, 9)),
        );
    }

    public function test_an_exclusive_granted_when_no_face_was_elite_at_night_takes_place_one_at_the_next_pass(): void
    {
        // Empty Élite tier at night: its slots cascade to the tiers below.
        $this->seedListing(0, elite: 0, pro: 7, starter: 6, free: 40);

        $this->nightly();

        $promoted = Face::query()->where('uuid', $this->tiers['starter'][5])->firstOrFail();
        FaceSubscription::query()->where('face_id', $promoted->id)->update([
            'status' => FaceSubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $promoted->id]);
        $promoted->update(['is_exclusive' => true]);

        $this->tick();

        $page = $this->page();
        $this->assertSame($promoted->uuid, $page[0]);
        $this->assertCount(16, $page);
    }

    public function test_an_exclusive_absent_from_the_nightly_generation_waits_for_the_next_night(): void
    {
        // Documented limit: the carousel only permutes the Faces of the
        // nightly generation. A Face created after it has no rank to lift.
        $this->seedListing(0);
        $this->nightly();

        $late = $this->makeFace('elite', ['is_exclusive' => true]);

        $this->tick();
        $this->assertNotContains($late->uuid, $this->page());

        $this->travel(1)->days();
        $this->nightly();
        $this->assertSame($late->uuid, $this->page()[0]);
    }

    public function test_filtered_results_put_a_matching_exclusive_at_the_top_of_page_one(): void
    {
        $flagged = $this->seedListing(4);
        Face::query()->update(['ville' => 'Parakou']);
        Face::query()->whereKey($flagged[2]->id)->update(['ville' => 'Cotonou']);
        foreach (array_slice($this->tiers['free'], 0, 20) as $uuid) {
            Face::query()->where('uuid', $uuid)->update(['ville' => 'Cotonou']);
        }
        foreach (array_slice($this->tiers['elite'], 0, 3) as $uuid) {
            Face::query()->where('uuid', $uuid)->update(['ville' => 'Cotonou']);
        }

        $this->nightly();
        $this->tick();

        $page = $this->page(1, ['ville' => 'Cotonou']);
        $this->assertSame($flagged[2]->uuid, $page[0]);
        $this->assertSame(['elite', 'elite', 'elite', 'elite'], $this->tiersOf(array_slice($page, 0, 4)));
    }

    public function test_kill_switch_serves_the_nightly_generation_with_the_exclusives_first(): void
    {
        $exclusives = $this->uuids($this->seedListing(4));
        config(['face_listing_rotation.tick_minutes' => 0]);

        $this->nightly();
        $this->tick();
        $this->tick();

        $page = $this->page();
        $this->assertSameSet($exclusives, array_slice($page, 0, 4));
        $this->assertSame(
            ['elite', 'elite', 'elite', 'elite', 'elite', 'pro', 'pro', 'pro', 'pro', 'starter', 'starter', 'free'],
            $this->tiersOf(array_slice($page, 4)),
        );
    }

    public function test_with_empty_middle_tiers_the_exclusives_lead_and_the_other_elites_keep_rotating(): void
    {
        // Production shape of 2026-07-22: 15 Élites, no Pro, no Starter. The
        // empty tiers' slots go up to the Élites (11 rotating + 4 exclusives).
        $exclusives = $this->uuids($this->seedListing(4, elite: 15, pro: 0, starter: 0, free: 50));

        $this->nightly();
        $before = $this->page();

        $this->tick();
        $after = $this->page();

        foreach ([$before, $after] as $page) {
            $this->assertSameSet($exclusives, array_slice($page, 0, 4));
            $this->assertSame(array_fill(0, 15, 'elite'), $this->tiersOf(array_slice($page, 0, 15)));
            $this->assertSame('free', $this->tiersOf([$page[15]])[0]);
        }

        // The 11 rotating Élites are all on screen; their order still moves.
        $this->assertNotSame(array_slice($before, 4, 11), array_slice($after, 4, 11));
    }
}
