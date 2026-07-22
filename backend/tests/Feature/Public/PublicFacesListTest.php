<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use App\Services\FaceListingRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicFacesListTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_list_of_faces_without_authentication(): void
    {
        // Create test faces with users
        for ($i = 0; $i < 20; $i++) {
            $face = Face::factory()->create(['is_available' => true]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
        }

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'username',
                        'prenom',
                        'ville',
                        'categories',
                        'is_available',
                        'profile_photo_thumbnail_url',
                        'average_rating',
                        'has_elite_badge',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'message',
            ]);

        // Default pagination is 16 items (full grid rows, commit 55895ef9)
        $this->assertEquals(16, $response->json('meta.per_page'));
        $this->assertCount(16, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_only_includes_public_safe_fields_in_response(): void
    {
        $face = Face::factory()->create([
            'nom' => 'TestNom',
            'prenom' => 'TestPrenom',
            'username' => 'testuser',
            'bio' => 'This is a bio',
            'tarif_horaire' => 50000,
            'tarif_journalier' => 200000,
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
            'is_available' => true,
            'categories' => [FaceCategory::ACTEUR->value],
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();

        $faceData = $response->json('data.0');

        // Should include public fields
        $this->assertArrayHasKey('id', $faceData);
        $this->assertArrayHasKey('prenom', $faceData);
        $this->assertArrayHasKey('ville', $faceData);
        $this->assertArrayHasKey('categories', $faceData);
        $this->assertArrayHasKey('is_available', $faceData);
        $this->assertArrayHasKey('profile_photo_thumbnail_url', $faceData);
        $this->assertArrayHasKey('average_rating', $faceData);

        // nom is now public (needed for search result context)
        $this->assertArrayHasKey('nom', $faceData);

        // username is now public (needed for slug-based URLs)
        $this->assertArrayHasKey('username', $faceData);

        // Should NOT include sensitive fields
        $this->assertArrayNotHasKey('bio', $faceData);
        $this->assertArrayNotHasKey('tarif_horaire', $faceData);
        $this->assertArrayNotHasKey('tarif_journalier', $faceData);
        $this->assertArrayNotHasKey('is_featured', $faceData);
        $this->assertArrayNotHasKey('quartier', $faceData);
        $this->assertArrayNotHasKey('pays', $faceData);
        $this->assertArrayNotHasKey('whatsapp_number', $faceData);
    }

    public function test_supports_custom_per_page_parameter_with_max_30(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $face = Face::factory()->create(['is_available' => true]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
        }

        // Request 30 items (max allowed)
        $response = $this->getJson('/api/v1/public/faces?per_page=30');

        $response->assertOk();
        $this->assertEquals(30, $response->json('meta.per_page'));
        $this->assertCount(30, $response->json('data'));

        // Request 50 items (should cap at 30)
        $response = $this->getJson('/api/v1/public/faces?per_page=50');

        $response->assertOk();
        $this->assertEquals(30, $response->json('meta.per_page'));
    }

    public function test_supports_page_parameter_for_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $face = Face::factory()->create(['is_available' => true]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
        }

        $response = $this->getJson('/api/v1/public/faces?page=2&per_page=15');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(5, $response->json('data')); // Remaining 5 items
    }

    public function test_returns_empty_data_array_when_no_faces_exist(): void
    {
        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'total' => 0,
                ],
            ]);
    }

    public function test_does_not_require_authentication(): void
    {
        $face = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // No auth token, no actingAs - should still work
        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
    }

    public function test_response_includes_correct_data_types(): void
    {
        $face = Face::factory()->create([
            'prenom' => 'Adjoua',
            'ville' => 'Cotonou',
            'categories' => [FaceCategory::ACTEUR->value],
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();

        $faceData = $response->json('data.0');

        $this->assertIsString($faceData['id']);
        $this->assertIsString($faceData['prenom']);
        $this->assertIsString($faceData['ville']);
        $this->assertIsArray($faceData['categories']);
        $this->assertIsBool($faceData['is_available']);
    }

    public function test_faces_without_profile_photo_have_null_thumbnail_url(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();

        $faceData = $response->json('data.0');
        $this->assertNull($faceData['profile_photo_thumbnail_url']);
    }

    public function test_success_message_is_returned(): void
    {
        $face = Face::factory()->create(['is_available' => true]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
        $this->assertNotEmpty($response->json('message'));
    }

    // ─── Filter Tests ─────────────────────────────────────────────────

    public function test_filters_faces_by_categorie(): void
    {
        $acteur = Face::factory()->create(['categories' => [FaceCategory::ACTEUR->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $acteur->id]);

        $mannequin = Face::factory()->create(['categories' => [FaceCategory::MANNEQUIN->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $mannequin->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($acteur->uuid, $response->json('data.0.id'));
    }

    public function test_filters_faces_by_niche(): void
    {
        $beaute = Face::factory()->create(['niches' => [FaceNiche::BEAUTE->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $beaute->id]);

        $mode = Face::factory()->create(['niches' => [FaceNiche::MODE->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $mode->id]);

        $response = $this->getJson('/api/v1/public/faces?niche=beaute');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($beaute->uuid, $response->json('data.0.id'));
    }

    public function test_filters_faces_by_ville_exact_match(): void
    {
        $cotonou = Face::factory()->create(['ville' => 'Cotonou']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $cotonou->id]);

        $parakou = Face::factory()->create(['ville' => 'Parakou']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $parakou->id]);

        $response = $this->getJson('/api/v1/public/faces?ville=Cotonou');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($cotonou->uuid, $response->json('data.0.id'));
    }

    public function test_returns_422_for_invalid_ville_filter(): void
    {
        $response = $this->getJson('/api/v1/public/faces?ville=Coto');

        $response->assertUnprocessable();
    }

    public function test_combines_multiple_filters_with_and_logic(): void
    {
        $match = Face::factory()->create([
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::BEAUTE->value],
            'ville' => 'Cotonou',
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $match->id]);

        $noMatch = Face::factory()->create([
            'categories' => [FaceCategory::ACTEUR->value],
            'niches' => [FaceNiche::MODE->value],
            'ville' => 'Cotonou',
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $noMatch->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&niche=beaute&ville=Cotonou');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($match->uuid, $response->json('data.0.id'));
    }

    public function test_returns_empty_results_with_non_matching_filters(): void
    {
        $face = Face::factory()->create(['categories' => [FaceCategory::ACTEUR->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=mannequin');

        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('data'));
    }

    public function test_returns_422_for_invalid_categorie_filter(): void
    {
        $response = $this->getJson('/api/v1/public/faces?categorie=invalid_value');

        $response->assertUnprocessable();
    }

    public function test_returns_422_for_invalid_niche_filter(): void
    {
        $response = $this->getJson('/api/v1/public/faces?niche=invalid_value');

        $response->assertUnprocessable();
    }

    public function test_pagination_works_with_active_filters(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $face = Face::factory()->create(['categories' => [FaceCategory::ACTEUR->value]]);
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        }

        // Add a non-matching face
        $other = Face::factory()->create(['categories' => [FaceCategory::MANNEQUIN->value]]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&per_page=10&page=2');

        $response->assertOk();
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_no_filters_returns_all_faces(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $face = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        }

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
        $this->assertEquals(5, $response->json('meta.total'));
    }

    // ─── Search Tests ─────────────────────────────────────────────────

    public function test_search_by_prenom_returns_matching_faces(): void
    {
        $adjoua = Face::factory()->create(['prenom' => 'Adjoua']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $adjoua->id]);

        $kofi = Face::factory()->create(['prenom' => 'Kofi']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $kofi->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Adjoua');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($adjoua->uuid, $response->json('data.0.id'));
    }

    public function test_search_by_nom_returns_matching_faces(): void
    {
        $face = Face::factory()->create(['nom' => 'Dossou']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $other = Face::factory()->create(['nom' => 'Agbangla']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Dossou');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_by_username_returns_matching_faces(): void
    {
        $face = Face::factory()->create(['username' => 'talent_adjoua']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $other = Face::factory()->create(['username' => 'star_kofi']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $response = $this->getJson('/api/v1/public/faces?search=talent_adjoua');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_by_bio_keyword_returns_matching_faces(): void
    {
        $face = Face::factory()->create(['bio' => 'Passionnée de beauté et de mode']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $other = Face::factory()->create(['bio' => 'Comédien professionnel']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $response = $this->getJson('/api/v1/public/faces?search=beauté');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_is_case_insensitive(): void
    {
        $face = Face::factory()->create(['prenom' => 'Adjoua']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $response = $this->getJson('/api/v1/public/faces?search=adjoua');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_with_partial_match_works(): void
    {
        $face = Face::factory()->create(['prenom' => 'Adjoua']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Adj');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_combined_with_category_filter_uses_and_logic(): void
    {
        $match = Face::factory()->create([
            'prenom' => 'Adjoua',
            'categories' => [FaceCategory::ACTEUR->value],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $match->id]);

        $nameMatchOnly = Face::factory()->create([
            'prenom' => 'Adjoua',
            'categories' => [FaceCategory::MANNEQUIN->value],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $nameMatchOnly->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Adjoua&categorie=acteur');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($match->uuid, $response->json('data.0.id'));
    }

    public function test_search_with_no_results_returns_empty_array(): void
    {
        $face = Face::factory()->create(['prenom' => 'Kofi']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $response = $this->getJson('/api/v1/public/faces?search=ZZNONEXISTENT');

        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'));
        $this->assertEmpty($response->json('data'));
    }

    public function test_search_is_accent_insensitive(): void
    {
        $face = Face::factory()->create(['bio' => 'Passionnée de beauté et de mode']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        // Search without accent should match text with accent (utf8mb4_unicode_ci collation)
        $response = $this->getJson('/api/v1/public/faces?search=beaute');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($face->uuid, $response->json('data.0.id'));
    }

    public function test_search_with_less_than_2_chars_returns_422(): void
    {
        $response = $this->getJson('/api/v1/public/faces?search=A');

        $response->assertUnprocessable();
    }

    public function test_search_with_special_characters_is_safe(): void
    {
        $face = Face::factory()->create(['prenom' => 'Test']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        // These should not cause SQL errors - % and _ are escaped
        $response = $this->getJson('/api/v1/public/faces?search=%25test');
        $response->assertOk();

        $response = $this->getJson('/api/v1/public/faces?search=test_injection');
        $response->assertOk();
    }

    public function test_pagination_works_with_active_search(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $face = Face::factory()->create(['prenom' => 'Adjoua']);
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        }

        // Add a non-matching face
        $other = Face::factory()->create(['prenom' => 'Kofi']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Adjoua&per_page=10&page=2');

        $response->assertOk();
        $this->assertEquals(20, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_no_search_param_returns_all_faces_backward_compatible(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $face = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        }

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
        $this->assertEquals(3, $response->json('meta.total'));
    }

    // ─── Response Shape (FEATURE-FP-1.6) ──────────────────────────────

    public function test_public_response_omits_subscription_fields_for_subscription_featured_face(): void
    {
        $face = Face::factory()->create([
            'is_featured' => false,
            'profile_photo' => 'p.jpg',
            'profile_photo_thumbnail' => 'p-thumb.jpg',
            'tarif_journalier' => 100000,
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $response = $this->getJson('/api/v1/public/faces');
        $response->assertOk();
        $faceData = $response->json('data.0');

        $this->assertArrayNotHasKey('is_featured', $faceData);
        $this->assertArrayNotHasKey('is_featured_by_subscription', $faceData);
        $this->assertArrayNotHasKey('subscription_tier', $faceData);
        $this->assertArrayNotHasKey('subscriptions', $faceData);
        $this->assertArrayNotHasKey('active_subscription', $faceData);
    }

    // ─── Materialized Listing Rotation ─────────────────────────────────
    //
    // The controller ORDER BY follows the rank materialized nightly by
    // `faces:rebuild-listing-ranks` (rank IS NULL, rank ASC, id DESC).
    // Tier/rotation SEMANTICS (quotas, WRR, LRU, featured, photo-less) are
    // covered by FaceListingRankingServiceTest and
    // RebuildFaceListingRanksCommandTest; here we prove the controller
    // faithfully follows the materialized rank and nothing else.

    /**
     * Create a Face attached to an active User (publicly listable).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeListedFace(array $attributes = []): Face
    {
        return Face::factory()->withActiveUser()->create($attributes);
    }

    /**
     * Seed one ranking generation directly (rank 1 = first array entry).
     *
     * @param  list<int>  $faceIdsInRankOrder
     */
    private function seedRankGeneration(int $generation, array $faceIdsInRankOrder, ?string $source = null): void
    {
        $rows = [];
        foreach ($faceIdsInRankOrder as $index => $faceId) {
            $rows[] = [
                'generation' => $generation,
                'face_id' => $faceId,
                'rank' => $index + 1,
                // NULL by default: a generation with no `source` is not
                // identifiable as the nightly base, which is exactly the state
                // the pre-carousel tests exercise (the filtered path then
                // falls back to MAX(generation)).
                'source' => $source,
            ];
        }

        DB::table('face_listing_ranks')->insert($rows);
    }

    public function test_default_per_page_matches_the_page_one_exposure_window(): void
    {
        $this->makeListedFace();

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
        // Coupling guard: the rebuild stamps the first PAGE_ONE_WINDOW Faces
        // as "page-1 exposed" BECAUSE that window is the default page size.
        // If the public default per_page ever changes, change both together.
        $this->assertSame(
            FaceListingRankingService::PAGE_ONE_WINDOW,
            $response->json('meta.per_page'),
        );
    }

    public function test_list_order_follows_materialized_rank_after_command_run(): void
    {
        $elite = $this->makeListedFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);
        $pro = $this->makeListedFace();
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);
        $this->makeListedFace();
        $this->makeListedFace(['is_featured' => true]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $rankedIds = DB::table('face_listing_ranks')
            ->orderBy('rank')
            ->pluck('face_id');
        $facesById = Face::query()->findMany($rankedIds)->keyBy('id');
        $expected = $rankedIds->map(fn ($id) => $facesById[(int) $id]->uuid)->all();

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame($expected, array_column($response->json('data'), 'id'));
    }

    public function test_list_order_follows_seeded_rank_regardless_of_profile_attributes(): void
    {
        // Deliberately anti-correlated with every legacy FP-2.6 sort key:
        // the WORST profile (bare, old, unfeatured) gets rank 1, the best
        // (featured, photo, tarif, newest) gets rank 3. Rank must win.
        $bare = $this->makeListedFace([
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDays(5),
        ]);
        $middle = $this->makeListedFace([
            'profile_photo' => 'photo.jpg',
            'profile_photo_thumbnail' => 'photo-thumb.jpg',
            'created_at' => now()->subDays(2),
        ]);
        $best = $this->makeListedFace([
            'is_featured' => true,
            'profile_photo' => 'best.jpg',
            'profile_photo_thumbnail' => 'best-thumb.jpg',
            'tarif_journalier' => 120000,
            'created_at' => now()->subHour(),
        ]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $best->id]);

        $this->seedRankGeneration(1, [$bare->id, $middle->id, $best->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$bare->uuid, $middle->uuid, $best->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_only_the_max_generation_ranks_drive_the_order(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();

        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id]);
        $this->seedRankGeneration(2, [$c->id, $a->id, $b->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$c->uuid, $a->uuid, $b->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_face_created_after_rebuild_falls_to_the_end_of_the_list(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $this->seedRankGeneration(1, [$b->id, $a->id]);

        // Created after the rebuild: no rank row. Under the pre-rotation
        // id DESC fallback it would come FIRST — `rank IS NULL` pushes it LAST.
        $late = $this->makeListedFace();

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$b->uuid, $a->uuid, $late->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_multiple_unranked_faces_order_by_id_desc_at_the_end(): void
    {
        $ranked = $this->makeListedFace();
        $this->seedRankGeneration(1, [$ranked->id]);

        $lateOlder = $this->makeListedFace();
        $lateNewer = $this->makeListedFace();

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$ranked->uuid, $lateNewer->uuid, $lateOlder->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_empty_ranks_table_falls_back_to_id_desc(): void
    {
        // Deployment window before the first rebuild: no rank rows at all.
        $first = $this->makeListedFace();
        $second = $this->makeListedFace();
        $third = $this->makeListedFace();

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$third->uuid, $second->uuid, $first->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_deactivated_user_face_is_excluded_despite_having_a_rank(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();
        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id]);

        // Deactivated AFTER the rebuild: exclusion is live (WHERE), the
        // stale rank row is a harmless hole in the ordering.
        User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $b->id)
            ->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(
            [$a->uuid, $c->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_reactivated_user_face_reappears_at_its_rank_position(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();
        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id]);

        $userQuery = User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $b->id);
        $userQuery->clone()->update(['is_active' => false]);
        $userQuery->clone()->update(['is_active' => true]);

        // The rank ORDERS, it never filters: the Face slots right back in.
        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$a->uuid, $b->uuid, $c->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_filters_preserve_rank_relative_order(): void
    {
        $acteurLow = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);
        $mannequin = $this->makeListedFace(['categories' => [FaceCategory::MANNEQUIN->value]]);
        $acteurTop = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);
        $this->seedRankGeneration(1, [$acteurTop->id, $mannequin->id, $acteurLow->id]);

        $acteurUnranked = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&per_page=10');

        $response->assertOk();
        // Subset of the ranked order (quotas not guaranteed — expected).
        $this->assertSame(
            [$acteurTop->uuid, $acteurLow->uuid, $acteurUnranked->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_search_preserves_rank_relative_order(): void
    {
        $adjouaLow = $this->makeListedFace(['prenom' => 'Adjoua', 'nom' => 'Dossou']);
        $kofi = $this->makeListedFace(['prenom' => 'Kofi']);
        $adjouaTop = $this->makeListedFace(['prenom' => 'Adjoua', 'nom' => 'Agbangla']);
        $this->seedRankGeneration(1, [$adjouaTop->id, $kofi->id, $adjouaLow->id]);

        $response = $this->getJson('/api/v1/public/faces?search=Adjoua&per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$adjouaTop->uuid, $adjouaLow->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_pagination_slices_the_ranked_order_without_duplicates(): void
    {
        $faces = [];
        for ($i = 0; $i < 20; $i++) {
            $faces[] = $this->makeListedFace();
        }

        // Deterministic shuffle (evens first, then odds) so the rank order
        // matches neither id ASC nor the id DESC fallback — the assertion
        // can only pass if the rank really drives the pagination.
        $order = [];
        foreach ($faces as $i => $face) {
            if ($i % 2 === 0) {
                $order[] = $face->id;
            }
        }
        foreach ($faces as $i => $face) {
            if ($i % 2 === 1) {
                $order[] = $face->id;
            }
        }
        $this->seedRankGeneration(1, $order);

        $uuidById = collect($faces)->keyBy('id')->map(fn (Face $f) => $f->uuid);

        $pageOne = $this->getJson('/api/v1/public/faces?per_page=15&page=1');
        $pageTwo = $this->getJson('/api/v1/public/faces?per_page=15&page=2');

        $pageOne->assertOk();
        $pageTwo->assertOk();

        $expected = array_map(fn (int $id) => $uuidById[$id], $order);
        $this->assertSame(array_slice($expected, 0, 15), array_column($pageOne->json('data'), 'id'));
        $this->assertSame(array_slice($expected, 15), array_column($pageTwo->json('data'), 'id'));
    }

    public function test_any_per_page_returns_a_prefix_of_the_ranked_order(): void
    {
        $faces = [];
        for ($i = 0; $i < 10; $i++) {
            $faces[] = $this->makeListedFace();
        }
        $this->seedRankGeneration(1, array_map(fn (Face $f) => $f->id, $faces));

        $full = $this->getJson('/api/v1/public/faces?per_page=30');
        $full->assertOk();
        $fullOrder = array_column($full->json('data'), 'id');

        foreach ([1, 4, 9] as $perPage) {
            $response = $this->getJson("/api/v1/public/faces?per_page={$perPage}");
            $response->assertOk();
            $this->assertSame(
                array_slice($fullOrder, 0, $perPage),
                array_column($response->json('data'), 'id'),
                "per_page={$perPage} must return the first {$perPage} ranked Faces.",
            );
        }
    }

    public function test_page_one_composition_respects_tier_quotas_after_command_run(): void
    {
        $tierByUuid = [];
        $seed = function (int $count, string $tier, ?callable $subscribe) use (&$tierByUuid): void {
            for ($i = 0; $i < $count; $i++) {
                $face = $this->makeListedFace();
                if ($subscribe !== null) {
                    $subscribe($face);
                }
                $tierByUuid[$face->uuid] = $tier;
            }
        };

        $seed(12, 'elite', fn (Face $f) => FaceSubscription::factory()->elite()->active()->create(['face_id' => $f->id]));
        $seed(6, 'pro', fn (Face $f) => FaceSubscription::factory()->pro()->active()->create(['face_id' => $f->id]));
        $seed(3, 'starter', fn (Face $f) => FaceSubscription::factory()->starter()->active()->create(['face_id' => $f->id]));
        $seed(3, 'free', null);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $response = $this->getJson('/api/v1/public/faces');
        $response->assertOk();

        $counts = ['elite' => 0, 'pro' => 0, 'starter' => 0, 'free' => 0];
        foreach (array_column($response->json('data'), 'id') as $uuid) {
            $counts[$tierByUuid[$uuid]]++;
        }

        // Deterministic smoothed-WRR split of the default 16-item page for
        // quotas 56/25/13/6: 9 élite, 4 pro, 2 starter, 1 free (PO-calibrated
        // so Starter visibly outranks Free).
        $this->assertSame(['elite' => 9, 'pro' => 4, 'starter' => 2, 'free' => 1], $counts);
    }

    public function test_expired_subscription_face_ranks_in_the_free_queue(): void
    {
        // KEEP this test even though tier classification is also covered at
        // the command level: with the élite and pro queues EMPTY, every WRR
        // slot here goes through the redistribution scan — this is the only
        // test in the suite where the DIRECTION of that scan is observable.
        //
        // That scan now targets the queue with the MOST Faces left to show,
        // ties broken by priority. Both queues hold exactly one Face here, so
        // the tie-break decides — and it must keep the paying starter ahead of
        // the expired élite sitting in the free queue. This is the test that
        // pins that commercial invariant against the redistribution rewrite.
        $expiredElite = $this->makeListedFace();
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $expiredElite->id]);
        $starter = $this->makeListedFace();
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        // No active row => free queue: the active starter outranks the
        // expired élite (equal depth, priority breaks the tie).
        $this->assertSame(
            [$starter->uuid, $expiredElite->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_rank_join_adds_no_per_row_queries(): void
    {
        // The rank is a single LEFT JOIN inside the paginated SELECT — it
        // must add zero per-Face queries. Proven by querying the same
        // 10-Face list with and without rank rows: the count is identical.
        $faces = [];
        for ($i = 0; $i < 10; $i++) {
            $faces[] = $this->makeListedFace();
        }

        DB::enableQueryLog();

        try {
            $this->getJson('/api/v1/public/faces?per_page=15')->assertOk();
            $withoutRanks = count(DB::getQueryLog());

            $this->seedRankGeneration(1, array_map(fn (Face $f) => $f->id, $faces));

            DB::flushQueryLog();
            $this->getJson('/api/v1/public/faces?per_page=15')->assertOk();
            $withRanks = count(DB::getQueryLog());

            $this->assertGreaterThan(0, $withoutRanks);
            $this->assertSame(
                $withoutRanks,
                $withRanks,
                'The materialized-rank join must not add a query per ranked Face.',
            );
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_listing_does_not_depend_on_tier_sort_priority_config(): void
    {
        // The listing no longer reads sort_priority at request time: the
        // order is the materialized rank. Swapping the (valid) priorities
        // AFTER the rebuild must leave the served order untouched. An
        // INVALID priority is a different story — buildCapabilities now
        // fail-louds on it everywhere, by design.
        $free = $this->makeListedFace();
        $elite = $this->makeListedFace();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        config([
            'face_subscription_tiers.tiers.elite.capabilities.sort_priority' => 4,
            'face_subscription_tiers.tiers.free.capabilities.sort_priority' => 1,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$elite->uuid, $free->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_failed_rebuild_keeps_serving_the_previous_generation(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();

        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(0);

        $before = $this->getJson('/api/v1/public/faces?per_page=10');
        $before->assertOk();

        // Break the quotas (sum 101): the next rebuild must fail loud and
        // roll back, leaving generation 1 as the served ranking.
        config(['face_subscription_tiers.tiers.elite.capabilities.listing_quota' => 61]);
        $this->artisan('faces:rebuild-listing-ranks')->assertExitCode(1);

        $after = $this->getJson('/api/v1/public/faces?per_page=10');
        $after->assertOk();

        $this->assertSame(
            array_column($before->json('data'), 'id'),
            array_column($after->json('data'), 'id'),
        );
    }

    // ===================================================================
    // FP-2.12.1 — has_elite_badge in public faces listing
    // ===================================================================

    public function test_public_list_emits_has_elite_badge_true_for_active_elite_face(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $this->getJson('/api/v1/public/faces')
            ->assertOk()
            ->assertJsonPath('data.0.has_elite_badge', true);
    }

    public function test_public_list_emits_has_elite_badge_false_for_non_elite_tiers(): void
    {
        // Free (no subscription)
        $free = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $free->id]);

        // Starter active
        $starter = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        // Pro active
        $pro = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $pro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);

        $response = $this->getJson('/api/v1/public/faces')->assertOk();

        // Élite-bucket is empty so the 3 returned rows are all non-Élite.
        $this->assertCount(3, $response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertFalse($row['has_elite_badge'], "Face {$row['username']} should not have elite badge");
        }
    }

    public function test_public_list_emits_has_elite_badge_false_for_expired_and_cancelled_elite(): void
    {
        $expired = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $expired->id]);
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $expired->id]);

        $cancelled = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $cancelled->id]);
        FaceSubscription::factory()->elite()->cancelled()->create(['face_id' => $cancelled->id]);

        $response = $this->getJson('/api/v1/public/faces')->assertOk();

        $this->assertCount(2, $response->json('data'));
        foreach ($response->json('data') as $row) {
            $this->assertFalse($row['has_elite_badge'], "Non-Active Élite face {$row['username']} must not earn the badge");
        }
    }

    // ─── Carousel: which generation the request is served from ────────

    public function test_unfiltered_list_serves_the_latest_generation_and_reports_it(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();

        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id], 'nightly');
        $this->seedRankGeneration(2, [$c->id, $b->id, $a->id], 'tick');

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$c->uuid, $b->uuid, $a->uuid],
            array_column($response->json('data'), 'id'),
        );
        // The served generation is echoed back so the client can pin it for
        // its next pages — it is NOT a URL parameter of the public page.
        $this->assertSame(2, $response->json('meta.generation'));
    }

    public function test_a_filtered_list_is_served_by_the_nightly_base_not_by_a_tick(): void
    {
        // A filtered result is a search, not a shop window: it must not
        // reshuffle under the visitor every five minutes.
        $a = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);
        $b = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);
        $c = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);

        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id], 'nightly');
        $this->seedRankGeneration(2, [$c->id, $b->id, $a->id], 'tick');

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$a->uuid, $b->uuid, $c->uuid],
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(1, $response->json('meta.generation'));
    }

    public function test_a_filtered_list_ignores_a_pinned_tick_generation(): void
    {
        $a = $this->makeListedFace(['ville' => 'Cotonou']);
        $b = $this->makeListedFace(['ville' => 'Cotonou']);

        $this->seedRankGeneration(1, [$a->id, $b->id], 'nightly');
        $this->seedRankGeneration(2, [$b->id, $a->id], 'tick');

        // Even explicitly asked for, a tick generation never orders a filtered
        // result: the nightly base wins.
        $response = $this->getJson('/api/v1/public/faces?ville=Cotonou&generation=2&per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$a->uuid, $b->uuid],
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(1, $response->json('meta.generation'));
    }

    public function test_a_filtered_list_falls_back_to_max_generation_without_a_nightly_base(): void
    {
        // Ranks written before the `source` column existed: nothing is
        // identifiable as the nightly base, and the filtered path degrades to
        // the pre-carousel behaviour instead of losing its ordering.
        $a = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);
        $b = $this->makeListedFace(['categories' => [FaceCategory::ACTEUR->value]]);

        $this->seedRankGeneration(1, [$a->id, $b->id]);
        $this->seedRankGeneration(2, [$b->id, $a->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$b->uuid, $a->uuid],
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(2, $response->json('meta.generation'));
    }

    public function test_pinning_a_generation_keeps_page_two_a_continuation_of_page_one(): void
    {
        $faces = [];
        for ($i = 0; $i < 20; $i++) {
            $faces[] = $this->makeListedFace();
        }
        $ids = array_map(fn (Face $f) => $f->id, $faces);
        $uuidById = collect($faces)->keyBy('id')->map(fn (Face $f) => $f->uuid);

        // Generation 1 and its rotated successor are DELIBERATELY reversed:
        // paging without a pin would mix the two windows.
        $this->seedRankGeneration(1, $ids, 'nightly');
        $this->seedRankGeneration(2, array_reverse($ids), 'tick');

        $pageOne = $this->getJson('/api/v1/public/faces?per_page=10&page=1');
        $pageOne->assertOk();
        $servedGeneration = $pageOne->json('meta.generation');
        $this->assertSame(2, $servedGeneration);

        // A rotation fires between the two requests.
        $this->seedRankGeneration(3, $ids, 'tick');

        $pageTwo = $this->getJson("/api/v1/public/faces?per_page=10&page=2&generation={$servedGeneration}");
        $pageTwo->assertOk();
        $this->assertSame($servedGeneration, $pageTwo->json('meta.generation'));

        $expected = array_map(fn (int $id) => $uuidById[$id], array_reverse($ids));
        $returned = array_merge(
            array_column($pageOne->json('data'), 'id'),
            array_column($pageTwo->json('data'), 'id'),
        );

        $this->assertSame($expected, $returned);
        $this->assertSame($returned, array_values(array_unique($returned)), 'No Face may appear twice.');
    }

    public function test_a_purged_pinned_generation_falls_back_silently(): void
    {
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();

        // Generation 1 has been purged by the retention window; only 5 exists.
        $this->seedRankGeneration(5, [$b->id, $a->id], 'tick');

        foreach ([1, 4, 99] as $purged) {
            $response = $this->getJson("/api/v1/public/faces?generation={$purged}&per_page=10");

            // Continuity is a courtesy, never a resource: no 4xx.
            $response->assertOk();
            $this->assertSame(5, $response->json('meta.generation'));
            $this->assertSame(
                [$b->uuid, $a->uuid],
                array_column($response->json('data'), 'id'),
            );
        }
    }

    public function test_generation_parameter_is_validated(): void
    {
        $this->makeListedFace();

        $this->getJson('/api/v1/public/faces?generation=0')->assertStatus(422);
        $this->getJson('/api/v1/public/faces?generation=abc')->assertStatus(422);
    }

    public function test_the_unpinned_listing_resolves_the_current_generation_inside_the_query(): void
    {
        // "The current window" must be resolved by a CORRELATED subquery, in
        // the same statement as the join — not frozen into a number read by a
        // separate SELECT. A retention purge committing between the two would
        // leave the join matching nothing and drop the WHOLE public listing
        // onto its id-DESC fallback, silently.
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $this->seedRankGeneration(7, [$b->id, $a->id], 'tick');

        /** @var list<string> $executed */
        $executed = [];
        DB::listen(function ($query) use (&$executed): void {
            $executed[] = $query->sql;
        });

        $this->getJson('/api/v1/public/faces?per_page=10')->assertOk();

        $joined = array_values(array_filter(
            $executed,
            fn (string $sql): bool => str_contains($sql, 'left join') && str_contains($sql, 'face_listing_ranks'),
        ));

        $this->assertNotEmpty($joined, 'The listing must join the ranking table.');
        foreach ($joined as $sql) {
            $this->assertStringContainsString(
                'select max(generation) from face_listing_ranks',
                $sql,
                'The unpinned listing must resolve MAX(generation) inside its own statement.',
            );
        }
    }

    public function test_a_pinned_generation_is_joined_on_its_exact_value(): void
    {
        // The mirror of the test above: an explicitly resolved generation
        // (pinned, or the nightly base of a filtered request) is a fixed
        // number — re-resolving MAX inside the query would defeat the pin.
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $this->seedRankGeneration(1, [$a->id, $b->id], 'nightly');
        $this->seedRankGeneration(2, [$b->id, $a->id], 'tick');

        /** @var list<string> $executed */
        $executed = [];
        DB::listen(function ($query) use (&$executed): void {
            $executed[] = $query->sql;
        });

        $response = $this->getJson('/api/v1/public/faces?per_page=10&generation=1');

        $response->assertOk();
        $this->assertSame(
            [$a->uuid, $b->uuid],
            array_column($response->json('data'), 'id'),
        );
        foreach ($executed as $sql) {
            $this->assertStringNotContainsString('select max(generation) from face_listing_ranks', $sql);
        }
    }

    public function test_an_empty_generation_parameter_is_ignored_not_rejected(): void
    {
        // A public page must not answer 422 because a proxy, or a hand-built
        // URL, kept the key without a value. Empty = no pin, same path.
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();

        $this->seedRankGeneration(1, [$a->id, $b->id], 'nightly');
        $this->seedRankGeneration(2, [$b->id, $a->id], 'tick');

        $response = $this->getJson('/api/v1/public/faces?generation=&per_page=10');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.generation'));
        $this->assertSame(
            [$b->uuid, $a->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_meta_generation_is_null_while_nothing_has_been_ranked(): void
    {
        $this->makeListedFace();

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();
        $this->assertNull($response->json('meta.generation'));
    }

    public function test_a_disabled_carousel_serves_the_nightly_order_whatever_the_pin(): void
    {
        // THE kill switch: `tick_minutes = 0` + `config:clear` must give back
        // EXACTLY the pre-carousel behaviour. Serving MAX(generation) would
        // keep serving the last permutation the rotation happened to write
        // before being switched off — the incident would survive its own fix.
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();
        $c = $this->makeListedFace();

        $this->seedRankGeneration(1, [$a->id, $b->id, $c->id], 'nightly');
        $this->seedRankGeneration(2, [$c->id, $b->id, $a->id], 'tick');
        $this->seedRankGeneration(3, [$b->id, $c->id, $a->id], 'tick');

        config(['face_listing_rotation.tick_minutes' => 0]);

        // No pin, a pin on the last tick, a pin on an older tick: the nightly
        // order wins in all three cases.
        foreach (['', '&generation=3', '&generation=2'] as $pin) {
            $response = $this->getJson("/api/v1/public/faces?per_page=10{$pin}");

            $response->assertOk();
            $this->assertSame(
                [$a->uuid, $b->uuid, $c->uuid],
                array_column($response->json('data'), 'id'),
                "A disabled carousel must serve the nightly order (pin: '{$pin}').",
            );
            $this->assertSame(1, $response->json('meta.generation'));
        }
    }

    public function test_a_disabled_carousel_without_a_nightly_base_keeps_the_legacy_order(): void
    {
        // Ranks written before the `source` column existed: nothing is
        // identifiable as nightly, so the kill switch degrades to
        // MAX(generation) — the pre-carousel behaviour, again.
        $a = $this->makeListedFace();
        $b = $this->makeListedFace();

        $this->seedRankGeneration(1, [$a->id, $b->id]);
        $this->seedRankGeneration(2, [$b->id, $a->id]);

        config(['face_listing_rotation.tick_minutes' => 0]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$b->uuid, $a->uuid],
            array_column($response->json('data'), 'id'),
        );
        $this->assertSame(2, $response->json('meta.generation'));
    }
}
