<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Constants\BeninCities;
use App\Models\Face;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFaceFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_categories_with_values_and_labels(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $categories = $response->json('data.categories');
        $this->assertNotEmpty($categories);

        // Verify structure
        $first = $categories[0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);

        // Verify known categories exist (all categories including influenceur for filtering)
        $values = array_column($categories, 'value');
        $this->assertContains('acteur', $values);
        $this->assertContains('mannequin', $values);
        $this->assertContains('influenceur', $values);
        $this->assertContains('createur', $values);
        $this->assertContains('figurant', $values);
        $this->assertContains('modele_photo', $values);
        $this->assertContains('egerie', $values);
        $this->assertContains('voix_off', $values);
    }

    public function test_returns_niches_with_values_and_labels(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $niches = $response->json('data.niches');
        $this->assertNotEmpty($niches);

        $values = array_column($niches, 'value');
        $this->assertContains('beaute', $values);
        $this->assertContains('nourriture', $values);
        $this->assertContains('decouverte', $values);
        $this->assertContains('mode', $values);
    }

    public function test_returns_static_benin_cities_list(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertSame(BeninCities::values(), $cities);
    }

    public function test_cities_are_sorted_alphabetically(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertEquals(BeninCities::values(), $cities);
    }

    public function test_returns_static_cities_even_when_faces_have_inconsistent_values(): void
    {
        $face = Face::factory()->create(['ville' => 'Valeur libre']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertSame(BeninCities::values(), $cities);
    }

    public function test_returns_benin_cities_when_no_faces_exist(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();
        $this->assertSame(BeninCities::values(), $response->json('data.cities'));
        // Categories and niches should still be present (from enums)
        $this->assertNotEmpty($response->json('data.categories'));
        $this->assertNotEmpty($response->json('data.niches'));
    }

    public function test_does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();
    }

    public function test_response_has_correct_structure(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => ['value', 'label'],
                    ],
                    'niches' => [
                        '*' => ['value', 'label'],
                    ],
                    'cities',
                ],
                'message',
            ]);
    }

    public function test_rate_limiting_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_filtering_by_single_category_matches_faces_with_multiple_categories(): void
    {
        // Face with multiple categories including 'acteur'
        $firstFace = Face::factory()->create([
            'categories' => ['acteur', 'mannequin'],
            'niches' => [],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $firstFace->id]);
        // Face without 'acteur'
        $secondFace = Face::factory()->create([
            'categories' => ['influenceur'],
            'niches' => [],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $secondFace->id]);
        // Face with 'acteur' only
        $thirdFace = Face::factory()->create([
            'categories' => ['acteur'],
            'niches' => [],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $thirdFace->id]);

        $response = $this->getJson('/api/v1/public/faces?categorie=acteur');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filtering_by_single_niche_matches_faces_with_multiple_niches(): void
    {
        $firstFace = Face::factory()->create([
            'categories' => [],
            'niches' => ['beaute', 'mode'],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $firstFace->id]);

        $secondFace = Face::factory()->create([
            'categories' => [],
            'niches' => ['nourriture'],
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $secondFace->id]);

        $response = $this->getJson('/api/v1/public/faces?niche=beaute');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
