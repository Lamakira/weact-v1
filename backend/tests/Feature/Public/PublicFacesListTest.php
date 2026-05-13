<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(
            [
                $newFeatured->uuid,
                $oldFeatured->uuid,
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
        $this->assertArrayNotHasKey('subscriptions', $faceData);
        $this->assertArrayNotHasKey('active_subscription', $faceData);
    }
}
