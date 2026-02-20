<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

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

        // Verify known categories exist
        $values = array_column($categories, 'value');
        $this->assertContains('acteur', $values);
        $this->assertContains('mannequin', $values);
        $this->assertContains('influenceur', $values);
        $this->assertContains('createur', $values);
        $this->assertContains('figurant', $values);
        $this->assertContains('modele_photo', $values);
        $this->assertContains('egerie', $values);
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

    public function test_returns_cities_from_existing_faces(): void
    {
        Face::factory()->create(['ville' => 'Cotonou']);
        Face::factory()->create(['ville' => 'Parakou']);
        Face::factory()->create(['ville' => 'Cotonou']); // duplicate

        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertCount(2, $cities);
        $this->assertContains('Cotonou', $cities);
        $this->assertContains('Parakou', $cities);
    }

    public function test_cities_are_sorted_alphabetically(): void
    {
        Face::factory()->create(['ville' => 'Porto-Novo']);
        Face::factory()->create(['ville' => 'Abomey-Calavi']);
        Face::factory()->create(['ville' => 'Cotonou']);

        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertEquals(['Abomey-Calavi', 'Cotonou', 'Porto-Novo'], $cities);
    }

    public function test_excludes_null_and_empty_cities(): void
    {
        Face::factory()->create(['ville' => 'Cotonou']);
        Face::factory()->create(['ville' => null]);
        Face::factory()->create(['ville' => '']);

        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();

        $cities = $response->json('data.cities');
        $this->assertCount(1, $cities);
        $this->assertEquals(['Cotonou'], $cities);
    }

    public function test_returns_empty_cities_when_no_faces_exist(): void
    {
        $response = $this->getJson('/api/v1/public/faces/options');

        $response->assertOk();
        $this->assertEmpty($response->json('data.cities'));
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
}
