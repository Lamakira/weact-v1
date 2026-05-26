<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
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

        // Default pagination is 15 items
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertCount(15, $response->json('data'));
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

    public function test_orders_faces_by_featured_then_profile_completeness_then_creation_date(): void
    {
        $oldFeatured = Face::factory()->create([
            'prenom' => 'Featured Old',
            'is_featured' => true,
            'profile_photo' => 'featured-old.jpg',
            'profile_photo_thumbnail' => 'featured-old-thumb.jpg',
            'tarif_journalier' => 120000,
            'created_at' => now()->subDays(5),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $oldFeatured->id,
        ]);

        $newFeatured = Face::factory()->create([
            'prenom' => 'Featured New',
            'is_featured' => true,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $newFeatured->id,
        ]);

        $photoAndTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subHours(6),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $photoAndTarif->id,
        ]);

        $photoOnly = Face::factory()->create([
            'prenom' => 'Photo Only',
            'profile_photo' => 'photo-only.jpg',
            'profile_photo_thumbnail' => 'photo-only-thumb.jpg',
            'tarif_journalier' => null,
            'created_at' => now()->subHours(4),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $photoOnly->id,
        ]);

        $rest = Face::factory()->create([
            'prenom' => 'Rest',
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subHours(2),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $rest->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        // FP-2.6: the profile-completeness key now applies within the featured
        // group too — oldFeatured (photo + tarif) outranks the newer newFeatured
        // (photo only). Non-featured Faces follow, ordered by completeness.
        $this->assertSame(
            [
                $oldFeatured->uuid,
                $newFeatured->uuid,
                $photoAndTarif->uuid,
                $photoOnly->uuid,
                $rest->uuid,
            ],
            array_column($response->json('data'), 'id')
        );
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

    // ─── Subscription-driven Featured Placement (FEATURE-FP-1.6) ──────

    public function test_subscription_active_face_floats_to_featured_bucket(): void
    {
        $subscriptionFeatured = Face::factory()->create([
            'prenom' => 'Sub Featured',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $subscriptionFeatured->id,
        ]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $subscriptionFeatured->id,
        ]);

        $photoAndTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'is_featured' => false,
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subHours(6),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $photoAndTarif->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();
        $this->assertSame(
            [$subscriptionFeatured->uuid, $photoAndTarif->uuid],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_expired_subscription_does_not_float_to_featured_bucket(): void
    {
        $expiredSubFace = Face::factory()->create([
            'prenom' => 'Expired Sub',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $expiredSubFace->id,
        ]);
        FaceSubscription::factory()->expired()->create([
            'face_id' => $expiredSubFace->id,
        ]);

        $photoAndTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'is_featured' => false,
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subHours(6),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $photoAndTarif->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();
        // Expired-sub Face is bucket 3 (no profile_photo, no tarif), Photo+Tarif is bucket 1.
        $this->assertSame(
            [$photoAndTarif->uuid, $expiredSubFace->uuid],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_cancelled_pending_failed_subscriptions_do_not_float_to_featured_bucket(): void
    {
        $cancelledSubFace = Face::factory()->create([
            'prenom' => 'Cancelled Sub',
            'is_featured' => false,
            'created_at' => now()->subDays(3),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $cancelledSubFace->id,
        ]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $cancelledSubFace->id]);

        $pendingSubFace = Face::factory()->create([
            'prenom' => 'Pending Sub',
            'is_featured' => false,
            'created_at' => now()->subDays(2),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $pendingSubFace->id,
        ]);
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $pendingSubFace->id]);

        $failedSubFace = Face::factory()->create([
            'prenom' => 'Failed Sub',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $failedSubFace->id,
        ]);
        FaceSubscription::factory()->failed()->create(['face_id' => $failedSubFace->id]);

        $manualFeaturedFace = Face::factory()->create([
            'prenom' => 'Manual',
            'is_featured' => true,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $manualFeaturedFace->id,
        ]);

        $photoAndTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'is_featured' => false,
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subHours(2),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $photoAndTarif->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();
        $this->assertSame(
            [
                $manualFeaturedFace->uuid,
                $photoAndTarif->uuid,
                $failedSubFace->uuid,
                $pendingSubFace->uuid,
                $cancelledSubFace->uuid,
            ],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_stale_active_subscription_with_past_expiry_does_not_float_to_featured_bucket(): void
    {
        $staleActiveFace = Face::factory()->create([
            'prenom' => 'Stale Active',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $staleActiveFace->id,
        ]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $staleActiveFace->id,
            'expires_at' => now()->subDay(),
        ]);

        $manualFeaturedFace = Face::factory()->create([
            'prenom' => 'Manual',
            'is_featured' => true,
            'created_at' => now()->subHours(6),
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $manualFeaturedFace->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();
        // Stale-active Face must NOT be in bucket 0.
        $this->assertSame(
            [$manualFeaturedFace->uuid, $staleActiveFace->uuid],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_manual_featured_and_subscription_featured_coexist_in_bucket_zero(): void
    {
        $manualOnly = Face::factory()->create([
            'prenom' => 'Manual Only',
            'is_featured' => true,
            'created_at' => now()->subDays(2),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $manualOnly->id]);

        $subscriptionOnly = Face::factory()->create([
            'prenom' => 'Subscription Only',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $subscriptionOnly->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $subscriptionOnly->id]);

        $bothFlags = Face::factory()->create([
            'prenom' => 'Both Flags',
            'is_featured' => true,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $bothFlags->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $bothFlags->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');
        $response->assertOk();
        $this->assertSame(
            [$bothFlags->uuid, $subscriptionOnly->uuid, $manualOnly->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

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

    // ─── Tier-Priority Ordering (FEATURE-FP-2.6) ──────────────────────

    public function test_faces_are_ordered_by_subscription_tier_priority(): void
    {
        $makeFace = function (string $prenom): Face {
            $face = Face::factory()->create([
                'prenom' => $prenom,
                'is_featured' => false,
                'profile_photo' => null,
                'profile_photo_thumbnail' => null,
                'tarif_journalier' => null,
                'created_at' => now()->subDay(),
            ]);
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            return $face;
        };

        $elite = $makeFace('Elite');
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);

        $pro = $makeFace('Pro');
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);

        $starter = $makeFace('Starter');
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        $free = $makeFace('Free'); // no subscription row

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$elite->uuid, $pro->uuid, $starter->uuid, $free->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_elite_subscriber_outranks_a_manually_featured_free_face(): void
    {
        $manualFeaturedFree = Face::factory()->create([
            'prenom' => 'Manual Featured Free',
            'is_featured' => true,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $manualFeaturedFree->id]);

        $elite = Face::factory()->create([
            'prenom' => 'Elite',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $elite->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$elite->uuid, $manualFeaturedFree->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_manual_is_featured_boosts_within_a_tier_bucket(): void
    {
        $featuredPro = Face::factory()->create([
            'prenom' => 'Featured Pro',
            'is_featured' => true,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $featuredPro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $featuredPro->id]);

        $plainPro = Face::factory()->create([
            'prenom' => 'Plain Pro',
            'is_featured' => false,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $plainPro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $plainPro->id]);

        $featuredFree = Face::factory()->create([
            'prenom' => 'Featured Free',
            'is_featured' => true,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $featuredFree->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$featuredPro->uuid, $plainPro->uuid, $featuredFree->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_profile_completeness_breaks_ties_within_a_tier_bucket(): void
    {
        $photoTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'is_featured' => false,
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $photoTarif->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $photoTarif->id]);

        $photoOnly = Face::factory()->create([
            'prenom' => 'Photo Only',
            'is_featured' => false,
            'profile_photo' => 'photo-only.jpg',
            'profile_photo_thumbnail' => 'photo-only-thumb.jpg',
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $photoOnly->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $photoOnly->id]);

        $bare = Face::factory()->create([
            'prenom' => 'Bare',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $bare->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $bare->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$photoTarif->uuid, $photoOnly->uuid, $bare->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_created_at_breaks_final_ties_within_a_tier_bucket(): void
    {
        $newer = Face::factory()->create([
            'prenom' => 'Newer Pro',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $newer->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $newer->id]);

        $older = Face::factory()->create([
            'prenom' => 'Older Pro',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDays(3),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $older->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $older->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$newer->uuid, $older->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_face_drops_to_free_bucket_after_subscription_expiration(): void
    {
        $expiredElite = Face::factory()->create([
            'prenom' => 'Expired Elite',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $expiredElite->id]);
        FaceSubscription::factory()->elite()->expired()->create(['face_id' => $expiredElite->id]);

        $starter = Face::factory()->create([
            'prenom' => 'Starter',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$starter->uuid, $expiredElite->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_face_ranks_by_active_row_after_a_tier_change(): void
    {
        // FP-2.5 tier-change outcome: the old Pro row is Cancelled, a fresh Élite
        // row is Active. The Face must now rank in the Élite bucket.
        $upgraded = Face::factory()->create([
            'prenom' => 'Upgraded',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $upgraded->id]);
        FaceSubscription::factory()->pro()->cancelled()->create(['face_id' => $upgraded->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $upgraded->id]);

        $pro = Face::factory()->create([
            'prenom' => 'Pro',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $pro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$upgraded->uuid, $pro->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_face_with_mixed_subscription_history_ranks_by_its_active_row(): void
    {
        $mixedHistory = Face::factory()->create([
            'prenom' => 'Mixed History',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $mixedHistory->id]);
        FaceSubscription::factory()->pro()->cancelled()->create(['face_id' => $mixedHistory->id]);
        FaceSubscription::factory()->starter()->expired()->create(['face_id' => $mixedHistory->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $mixedHistory->id]);

        $pro = Face::factory()->create([
            'prenom' => 'Pro',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $pro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$mixedHistory->uuid, $pro->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_cancelled_pending_failed_subscriptions_keep_face_in_free_bucket(): void
    {
        $freeBucketFace = Face::factory()->create([
            'prenom' => 'Free Bucket',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeBucketFace->id]);
        FaceSubscription::factory()->pro()->cancelled()->create(['face_id' => $freeBucketFace->id]);
        FaceSubscription::factory()->pro()->pendingPayment()->create(['face_id' => $freeBucketFace->id]);
        FaceSubscription::factory()->pro()->failed()->create(['face_id' => $freeBucketFace->id]);

        $starter = Face::factory()->create([
            'prenom' => 'Starter',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$starter->uuid, $freeBucketFace->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_stale_active_subscription_past_expiry_keeps_face_in_free_bucket(): void
    {
        $staleElite = Face::factory()->create([
            'prenom' => 'Stale Elite',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $staleElite->id]);
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $staleElite->id,
            'expires_at' => now()->subDay(),
        ]);

        $pro = Face::factory()->create([
            'prenom' => 'Pro',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $pro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $pro->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$pro->uuid, $staleElite->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_tier_priority_order_is_driven_by_config(): void
    {
        // Flip the configured priorities so Starter outranks Élite; the list order
        // must follow with zero code change (Product Decision #9).
        config(['face_subscription_tiers.tiers.starter.capabilities.sort_priority' => 1]);
        config(['face_subscription_tiers.tiers.elite.capabilities.sort_priority' => 3]);

        $elite = Face::factory()->create([
            'prenom' => 'Elite',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $elite->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $elite->id]);

        $starter = Face::factory()->create([
            'prenom' => 'Starter',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starter->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$starter->uuid, $elite->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_all_free_faces_order_by_featured_then_completeness_then_created_at(): void
    {
        $featured = Face::factory()->create([
            'prenom' => 'Featured Free',
            'is_featured' => true,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDays(4),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $featured->id]);

        $photoTarif = Face::factory()->create([
            'prenom' => 'Photo Tarif',
            'is_featured' => false,
            'profile_photo' => 'photo-tarif.jpg',
            'profile_photo_thumbnail' => 'photo-tarif-thumb.jpg',
            'tarif_journalier' => 95000,
            'created_at' => now()->subDays(3),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $photoTarif->id]);

        $photoOnly = Face::factory()->create([
            'prenom' => 'Photo Only',
            'is_featured' => false,
            'profile_photo' => 'photo-only.jpg',
            'profile_photo_thumbnail' => 'photo-only-thumb.jpg',
            'tarif_journalier' => null,
            'created_at' => now()->subDays(2),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $photoOnly->id]);

        $bare = Face::factory()->create([
            'prenom' => 'Bare',
            'is_featured' => false,
            'profile_photo' => null,
            'profile_photo_thumbnail' => null,
            'tarif_journalier' => null,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $bare->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$featured->uuid, $photoTarif->uuid, $photoOnly->uuid, $bare->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_full_mixed_tier_ordering(): void
    {
        $plainElite = Face::factory()->create([
            'prenom' => 'Plain Elite',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $plainElite->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $plainElite->id]);

        $featuredPro = Face::factory()->create([
            'prenom' => 'Featured Pro',
            'is_featured' => true,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $featuredPro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $featuredPro->id]);

        $plainPro = Face::factory()->create([
            'prenom' => 'Plain Pro',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $plainPro->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $plainPro->id]);

        $plainStarter = Face::factory()->create([
            'prenom' => 'Plain Starter',
            'is_featured' => false,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $plainStarter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $plainStarter->id]);

        $featuredFree = Face::factory()->create([
            'prenom' => 'Featured Free',
            'is_featured' => true,
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $featuredFree->id]);

        $response = $this->getJson('/api/v1/public/faces?per_page=10');

        $response->assertOk();
        $this->assertSame(
            [$plainElite->uuid, $featuredPro->uuid, $plainPro->uuid, $plainStarter->uuid, $featuredFree->uuid],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_tier_priority_ordering_adds_no_per_row_queries(): void
    {
        // The tier-priority ordering is a correlated subquery inside the single
        // paginated SELECT — it must add zero per-Face queries. Proven by
        // querying the same 10-Face list with and without active subscriptions:
        // the query count is identical. (The public list has a pre-existing
        // per-Face query cost unrelated to FP-2.6; this comparison cancels it.)
        $faces = [];
        for ($i = 0; $i < 10; $i++) {
            $face = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
            $faces[] = $face;
        }

        DB::enableQueryLog();

        try {
            $this->getJson('/api/v1/public/faces?per_page=15')->assertOk();
            $withoutSubscriptions = count(DB::getQueryLog());

            foreach ($faces as $face) {
                FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
            }

            DB::flushQueryLog();
            $this->getJson('/api/v1/public/faces?per_page=15')->assertOk();
            $withSubscriptions = count(DB::getQueryLog());

            $this->assertGreaterThan(0, $withoutSubscriptions);
            $this->assertSame(
                $withoutSubscriptions,
                $withSubscriptions,
                'The tier-priority ordering subquery must not add a query per subscribed Face.',
            );
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_public_faces_list_fails_loud_on_non_integer_tier_sort_priority(): void
    {
        // The tierSortPriority() guard must reject a non-integer config value
        // (here a fractional 1.9) instead of silently truncating it to 1 and
        // mis-ranking the tier — a broken config must surface, not sort wrong.
        config(['face_subscription_tiers.tiers.pro.capabilities.sort_priority' => 1.9]);

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $this->getJson('/api/v1/public/faces');
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
}
