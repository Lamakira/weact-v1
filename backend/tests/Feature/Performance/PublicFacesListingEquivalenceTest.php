<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Face;
use App\Models\User;
use App\Support\Sql;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B6: the public faces listing is driven by the rank index (deferred
 * join) but must return EXACTLY what the former single-statement query did:
 * same Faces, same order, same pagination meta.
 */
class PublicFacesListingEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Face> */
    private array $faces = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedFaces(30);
    }

    private function seedFaces(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $face = Face::factory()->withActiveUser()->create([
                'ville' => $i % 2 === 0 ? 'Cotonou' : 'Porto-Novo',
                'categories' => $i % 3 === 0 ? ['acteur', 'mannequin'] : ['influenceur'],
                'prenom' => 'Prenom'.$i,
            ]);
            $this->faces[] = $face;
        }

        // Four deactivated Faces (holes in the ranking, absent from the listing).
        foreach ([2, 5, 11, 20] as $index) {
            User::query()
                ->where('userable_type', Face::class)
                ->where('userable_id', $this->faces[$index]->id)
                ->update(['is_active' => false]);
        }

        // generation 1 (nightly): 18 Faces, including two inactive ones.
        $this->seedGeneration(1, 'nightly', [14, 3, 2, 27, 8, 0, 19, 5, 22, 1, 25, 9, 16, 28, 6, 12, 23, 4]);
        // generation 2 (tick, latest): a different permutation of 16 Faces.
        $this->seedGeneration(2, 'tick', [7, 26, 10, 0, 13, 29, 21, 3, 18, 1, 24, 15, 11, 17, 8, 4]);
    }

    /**
     * @param  list<int>  $faceIndexesInRankOrder
     */
    private function seedGeneration(int $generation, string $source, array $faceIndexesInRankOrder): void
    {
        $rows = [];
        foreach ($faceIndexesInRankOrder as $position => $faceIndex) {
            $rows[] = [
                'generation' => $generation,
                'face_id' => $this->faces[$faceIndex]->id,
                'rank' => $position + 1,
                'source' => $source,
            ];
        }
        DB::table('face_listing_ranks')->insert($rows);
    }

    /**
     * The former implementation, verbatim: one statement, LEFT JOIN on the
     * ranks, rank-null last, faces.id DESC tiebreak.
     *
     * @param  array<string, string>  $filters
     * @return array{ids: list<string>, total: int, last_page: int}
     */
    private function reference(int $generation, bool $latest, int $page, int $perPage, array $filters = []): array
    {
        $paginator = Face::query()
            ->select('faces.*')
            ->publiclyListable()
            ->when($filters['categorie'] ?? null, fn ($q, $cat) => $q->whereJsonContains('categories', $cat))
            ->when($filters['ville'] ?? null, fn ($q, $ville) => $q->where('ville', $ville))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $escaped = Sql::escapeLike($search);

                return $q->where(function ($query) use ($escaped) {
                    $query->where('prenom', 'like', "%{$escaped}%")
                        ->orWhere('username', 'like', "%{$escaped}%")
                        ->orWhere('bio', 'like', "%{$escaped}%");
                });
            })
            ->leftJoin('face_listing_ranks', function (JoinClause $join) use ($generation, $latest): void {
                $join->on('face_listing_ranks.face_id', '=', 'faces.id');

                if ($latest) {
                    $join->whereRaw('face_listing_ranks.generation = (select max(generation) from face_listing_ranks)');

                    return;
                }

                $join->where('face_listing_ranks.generation', '=', $generation);
            })
            ->orderByRaw('face_listing_ranks.rank is null')
            ->orderBy('face_listing_ranks.rank')
            ->orderBy('faces.id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'ids' => $paginator->getCollection()->pluck('uuid')->all(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{ids: list<string>, total: int, last_page: int}
     */
    private function endpoint(array $query): array
    {
        Cache::flush();
        $response = $this->getJson('/api/v1/public/faces?'.http_build_query($query))->assertOk();

        return [
            'ids' => array_column($response->json('data'), 'id'),
            'total' => $response->json('meta.total'),
            'last_page' => $response->json('meta.last_page'),
        ];
    }

    public function test_unfiltered_listing_matches_the_reference_on_every_page(): void
    {
        $perPage = 7;

        for ($page = 1; $page <= 6; $page++) {
            $this->assertSame(
                $this->reference(2, true, $page, $perPage),
                $this->endpoint(['per_page' => $perPage, 'page' => $page]),
                "latest generation, page {$page}",
            );
        }
    }

    public function test_unfiltered_listing_matches_the_reference_for_the_default_page_size(): void
    {
        $this->assertSame(
            $this->reference(2, true, 1, 16),
            $this->endpoint([]),
        );
        $this->assertSame(
            $this->reference(2, true, 2, 16),
            $this->endpoint(['page' => 2]),
        );
    }

    public function test_pinned_generation_matches_the_reference(): void
    {
        for ($page = 1; $page <= 4; $page++) {
            $this->assertSame(
                $this->reference(1, false, $page, 6),
                $this->endpoint(['per_page' => 6, 'page' => $page, 'generation' => 1]),
                "pinned generation 1, page {$page}",
            );
        }
    }

    public function test_filtered_listing_matches_the_reference_on_the_nightly_base(): void
    {
        foreach ([
            ['ville' => 'Cotonou'],
            ['ville' => 'Porto-Novo'],
            ['categorie' => 'acteur'],
            ['search' => 'Prenom1'],
            ['ville' => 'Cotonou', 'categorie' => 'influenceur'],
        ] as $filters) {
            for ($page = 1; $page <= 3; $page++) {
                $this->assertSame(
                    $this->reference(1, false, $page, 5, $filters),
                    $this->endpoint(['per_page' => 5, 'page' => $page, ...$filters]),
                    'filters '.json_encode($filters)." page {$page}",
                );
            }
        }
    }

    public function test_listing_without_any_rank_falls_back_to_id_desc(): void
    {
        DB::table('face_listing_ranks')->delete();

        $this->assertSame(
            $this->reference(0, false, 1, 10),
            $this->endpoint(['per_page' => 10]),
        );
        $this->assertSame(
            $this->reference(0, false, 3, 10),
            $this->endpoint(['per_page' => 10, 'page' => 3]),
        );
    }

    public function test_query_count_is_constant_and_small(): void
    {
        $this->listingQueryCount(); // warms the cached total
        $small = $this->listingQueryCount();
        $this->assertLessThanOrEqual(8, $small, 'First page of 16');

        $this->seedExtraFaces(20);
        $large = $this->listingQueryCount();

        $this->assertSame($small, $large, "Listing queries grew with rows: {$small} -> {$large}");
    }

    public function test_rating_aggregates_are_computed_for_the_page_ids_only(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/public/faces')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $withAggregates = array_values(array_filter($queries, fn (string $q): bool => str_contains($q, 'rating_candidature_sum')));

        $this->assertCount(1, $withAggregates);
        $this->assertMatchesRegularExpression('/where `faces`\.`id` in \(/', $withAggregates[0], 'Aggregates only for the ids of the page');
        $this->assertStringNotContainsString(' limit ', $withAggregates[0], 'No row sort/limit around the correlated subqueries');
    }

    public function test_total_is_cached_between_requests(): void
    {
        Cache::flush();
        $this->getJson('/api/v1/public/faces')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/public/faces')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $counts = array_filter($queries, fn (string $q): bool => str_starts_with($q, 'select count(*) as aggregate from `faces`'));
        $this->assertCount(0, $counts, 'The public total must come from the cache on the second request');
    }

    private function seedExtraFaces(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Face::factory()->withActiveUser()->create();
        }
    }

    private function listingQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/public/faces')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
