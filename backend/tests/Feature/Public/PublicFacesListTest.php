<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
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
                        'prenom',
                        'ville',
                        'categorie',
                        'categorie_label',
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
            'quartier' => 'Akpakpa',
            'pays' => 'Bénin',
            'is_available' => true,
            'categorie' => FaceCategory::ACTEUR,
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
        $this->assertArrayHasKey('categorie', $faceData);
        $this->assertArrayHasKey('categorie_label', $faceData);
        $this->assertArrayHasKey('is_available', $faceData);
        $this->assertArrayHasKey('profile_photo_thumbnail_url', $faceData);
        $this->assertArrayHasKey('average_rating', $faceData);

        // Should NOT include sensitive fields
        $this->assertArrayNotHasKey('nom', $faceData);
        $this->assertArrayNotHasKey('username', $faceData);
        $this->assertArrayNotHasKey('bio', $faceData);
        $this->assertArrayNotHasKey('tarif_horaire', $faceData);
        $this->assertArrayNotHasKey('tarif_journalier', $faceData);
        $this->assertArrayNotHasKey('quartier', $faceData);
        $this->assertArrayNotHasKey('pays', $faceData);
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
            'categorie' => FaceCategory::ACTEUR,
            'is_available' => true,
        ]);

        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->getJson('/api/v1/public/faces');

        $response->assertOk();

        $faceData = $response->json('data.0');

        $this->assertIsInt($faceData['id']);
        $this->assertIsString($faceData['prenom']);
        $this->assertIsString($faceData['ville']);
        $this->assertIsString($faceData['categorie']);
        $this->assertIsString($faceData['categorie_label']);
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
        $acteur = Face::factory()->create(['categorie' => FaceCategory::ACTEUR]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $acteur->id]);

        $mannequin = Face::factory()->create(['categorie' => FaceCategory::MANNEQUIN]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $mannequin->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($acteur->id, $response->json('data.0.id'));
    }

    public function test_filters_faces_by_niche(): void
    {
        $beaute = Face::factory()->create(['niche' => FaceNiche::BEAUTE]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $beaute->id]);

        $mode = Face::factory()->create(['niche' => FaceNiche::MODE]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $mode->id]);

        $response = $this->getJson('/api/v1/public/faces?niche=beaute');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($beaute->id, $response->json('data.0.id'));
    }

    public function test_filters_faces_by_ville_partial_match(): void
    {
        $cotonou = Face::factory()->create(['ville' => 'Cotonou']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $cotonou->id]);

        $parakou = Face::factory()->create(['ville' => 'Parakou']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $parakou->id]);

        $response = $this->getJson('/api/v1/public/faces?ville=Coto');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($cotonou->id, $response->json('data.0.id'));
    }

    public function test_combines_multiple_filters_with_and_logic(): void
    {
        $match = Face::factory()->create([
            'categorie' => FaceCategory::ACTEUR,
            'niche' => FaceNiche::BEAUTE,
            'ville' => 'Cotonou',
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $match->id]);

        $noMatch = Face::factory()->create([
            'categorie' => FaceCategory::ACTEUR,
            'niche' => FaceNiche::MODE,
            'ville' => 'Cotonou',
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $noMatch->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur&niche=beaute&ville=Cotonou');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($match->id, $response->json('data.0.id'));
    }

    public function test_returns_empty_results_with_non_matching_filters(): void
    {
        $face = Face::factory()->create(['categorie' => FaceCategory::ACTEUR]);
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
            $face = Face::factory()->create(['categorie' => FaceCategory::ACTEUR]);
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        }

        // Add a non-matching face
        $other = Face::factory()->create(['categorie' => FaceCategory::MANNEQUIN]);
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
}
