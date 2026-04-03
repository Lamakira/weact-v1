<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryNicheTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face user
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_can_get_categories_and_niches(): void
    {
        $this->face->update([
            'categories' => ['acteur', 'mannequin'],
            'niches' => ['beaute'],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/category-niche');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => ['value', 'label'],
                    ],
                    'niches' => [
                        '*' => ['value', 'label'],
                    ],
                ],
            ])
            ->assertJsonPath('data.categories.0.value', 'acteur')
            ->assertJsonPath('data.categories.0.label', 'Acteur')
            ->assertJsonPath('data.categories.1.value', 'mannequin')
            ->assertJsonPath('data.categories.1.label', 'Mannequin')
            ->assertJsonPath('data.niches.0.value', 'beaute')
            ->assertJsonPath('data.niches.0.label', 'Beauté');
    }

    public function test_can_update_categories_with_multiple_values(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['voix_off', 'createur'],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonPath('data.categories.0.value', 'voix_off')
            ->assertJsonPath('data.categories.0.label', 'Voix off')
            ->assertJsonPath('data.categories.1.value', 'createur')
            ->assertJsonPath('data.categories.1.label', 'Créateur de contenu')
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->face->refresh();
        $this->assertEquals(['voix_off', 'createur'], $this->face->categories);
    }

    public function test_can_update_niches_with_multiple_values(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niches' => ['mode', 'beaute'],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.niches')
            ->assertJsonPath('data.niches.0.value', 'mode')
            ->assertJsonPath('data.niches.0.label', 'Mode')
            ->assertJsonPath('data.niches.1.value', 'beaute')
            ->assertJsonPath('data.niches.1.label', 'Beauté')
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->face->refresh();
        $this->assertEquals(['mode', 'beaute'], $this->face->niches);
    }

    public function test_can_update_categories_and_niches_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['mannequin', 'egerie'],
                'niches' => ['nourriture', 'decouverte'],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonCount(2, 'data.niches')
            ->assertJsonPath('data.categories.0.value', 'mannequin')
            ->assertJsonPath('data.niches.0.value', 'nourriture');

        $this->face->refresh();
        $this->assertEquals(['mannequin', 'egerie'], $this->face->categories);
        $this->assertEquals(['nourriture', 'decouverte'], $this->face->niches);
    }

    public function test_can_update_with_single_category(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['acteur'],
            ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.value', 'acteur');
    }

    public function test_rejects_invalid_category_in_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['acteur', 'invalid_category'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['categories.1']);
    }

    public function test_rejects_invalid_niche_in_array(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niches' => ['invalid_niche'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['niches.0']);
    }

    public function test_rejects_non_array_categories(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => 'acteur',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['categories']);
    }

    public function test_can_clear_categories_with_null(): void
    {
        $this->face->update(['categories' => ['acteur']]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.categories', []);

        $this->face->refresh();
        $this->assertEquals([], $this->face->categories);
    }

    public function test_can_clear_categories_with_empty_array(): void
    {
        $this->face->update(['categories' => ['acteur']]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => [],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.categories', []);
    }

    public function test_can_clear_niches_with_null(): void
    {
        $this->face->update(['niches' => ['beaute']]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niches' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.niches', []);

        $this->face->refresh();
        $this->assertEquals([], $this->face->niches);
    }

    public function test_producer_cannot_access_category_niche_endpoint(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/category-niche');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_producer_cannot_update_category_niche(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['acteur'],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_category_niche(): void
    {
        $response = $this->getJson('/api/v1/face/category-niche');

        $response->assertUnauthorized();
    }

    public function test_options_categories_returns_correct_values(): void
    {
        $response = $this->getJson('/api/v1/face/options/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ])
            ->assertJsonCount(7, 'data');

        $categories = $response->json('data');

        $expectedCategories = [
            ['value' => 'acteur', 'label' => 'Acteur'],
            ['value' => 'createur', 'label' => 'Créateur de contenu'],
            ['value' => 'mannequin', 'label' => 'Mannequin'],
            ['value' => 'figurant', 'label' => 'Figurant'],
            ['value' => 'modele_photo', 'label' => 'Modèle Photo'],
            ['value' => 'egerie', 'label' => 'Égérie'],
            ['value' => 'voix_off', 'label' => 'Voix off'],
        ];

        $this->assertEquals($expectedCategories, $categories);
    }

    public function test_options_niches_returns_correct_values(): void
    {
        $response = $this->getJson('/api/v1/face/options/niches');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ])
            ->assertJsonCount(4, 'data');

        $niches = $response->json('data');

        $expectedNiches = [
            ['value' => 'beaute', 'label' => 'Beauté'],
            ['value' => 'nourriture', 'label' => 'Nourriture'],
            ['value' => 'decouverte', 'label' => 'Découverte'],
            ['value' => 'mode', 'label' => 'Mode'],
        ];

        $this->assertEquals($expectedNiches, $niches);
    }

    public function test_options_endpoints_are_public(): void
    {
        $categoriesResponse = $this->getJson('/api/v1/face/options/categories');
        $categoriesResponse->assertOk();

        $nichesResponse = $this->getJson('/api/v1/face/options/niches');
        $nichesResponse->assertOk();
    }

    public function test_can_set_all_category_values(): void
    {
        $allCategories = ['acteur', 'createur', 'mannequin', 'figurant', 'modele_photo', 'egerie', 'voix_off'];

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => $allCategories,
            ]);

        $response->assertOk()
            ->assertJsonCount(7, 'data.categories');
    }

    public function test_cannot_set_influenceur_category(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['acteur', 'influenceur'],
            ]);

        $response->assertUnprocessable();
    }

    public function test_can_set_all_niche_values(): void
    {
        $allNiches = ['beaute', 'nourriture', 'decouverte', 'mode'];

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niches' => $allNiches,
            ]);

        $response->assertOk()
            ->assertJsonCount(4, 'data.niches');
    }

    public function test_returns_empty_arrays_for_new_face(): void
    {
        $newFace = Face::factory()->create(['categories' => [], 'niches' => []]);
        $newUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $newFace->id,
        ]);

        $response = $this->actingAs($newUser)
            ->getJson('/api/v1/face/category-niche');

        $response->assertOk()
            ->assertJsonPath('data.categories', [])
            ->assertJsonPath('data.niches', []);
    }

    public function test_partial_update_preserves_other_field(): void
    {
        $this->face->update([
            'categories' => ['acteur'],
            'niches' => ['beaute', 'mode'],
        ]);

        // Update only categories
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categories' => ['mannequin'],
            ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.value', 'mannequin');

        // Niches should remain unchanged
        $this->face->refresh();
        $this->assertEquals(['beaute', 'mode'], $this->face->niches);
    }
}
