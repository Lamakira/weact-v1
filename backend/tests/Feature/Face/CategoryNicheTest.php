<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
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

    public function test_can_get_category_and_niche(): void
    {
        $this->face->update([
            'categorie' => FaceCategory::ACTEUR,
            'niche' => FaceNiche::BEAUTE,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/category-niche');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categorie',
                    'categorie_label',
                    'niche',
                    'niche_label',
                ],
            ])
            ->assertJsonPath('data.categorie', 'acteur')
            ->assertJsonPath('data.categorie_label', 'Acteur')
            ->assertJsonPath('data.niche', 'beaute')
            ->assertJsonPath('data.niche_label', 'Beauté');
    }

    public function test_can_update_categorie_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categorie' => 'influenceur',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.categorie', 'influenceur')
            ->assertJsonPath('data.categorie_label', 'Influenceur')
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'categorie' => 'influenceur',
        ]);
    }

    public function test_can_update_niche_successfully(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niche' => 'mode',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.niche', 'mode')
            ->assertJsonPath('data.niche_label', 'Mode')
            ->assertJsonPath('message', 'Profil mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'niche' => 'mode',
        ]);
    }

    public function test_can_update_categorie_and_niche_together(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categorie' => 'mannequin',
                'niche' => 'nourriture',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.categorie', 'mannequin')
            ->assertJsonPath('data.categorie_label', 'Mannequin')
            ->assertJsonPath('data.niche', 'nourriture')
            ->assertJsonPath('data.niche_label', 'Nourriture');

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'categorie' => 'mannequin',
            'niche' => 'nourriture',
        ]);
    }

    public function test_rejects_invalid_categorie_enum_value(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categorie' => 'invalid_category',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['categorie'])
            ->assertJsonPath('errors.categorie.0', "La catégorie sélectionnée n'est pas valide");
    }

    public function test_rejects_invalid_niche_enum_value(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niche' => 'invalid_niche',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['niche'])
            ->assertJsonPath('errors.niche.0', "La niche sélectionnée n'est pas valide");
    }

    public function test_can_clear_categorie(): void
    {
        $this->face->update(['categorie' => FaceCategory::ACTEUR]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'categorie' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.categorie', null)
            ->assertJsonPath('data.categorie_label', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'categorie' => null,
        ]);
    }

    public function test_can_clear_niche(): void
    {
        $this->face->update(['niche' => FaceNiche::BEAUTE]);

        $response = $this->actingAs($this->faceUser)
            ->putJson('/api/v1/face/category-niche', [
                'niche' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.niche', null)
            ->assertJsonPath('data.niche_label', null);

        $this->assertDatabaseHas('faces', [
            'id' => $this->face->id,
            'niche' => null,
        ]);
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
                'categorie' => 'acteur',
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
            ->assertJsonCount(5, 'data');

        $categories = $response->json('data');

        $expectedCategories = [
            ['value' => 'acteur', 'label' => 'Acteur'],
            ['value' => 'influenceur', 'label' => 'Influenceur'],
            ['value' => 'createur', 'label' => 'Créateur'],
            ['value' => 'mannequin', 'label' => 'Mannequin'],
            ['value' => 'figurant', 'label' => 'Figurant'],
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
        // Categories endpoint should work without authentication
        $categoriesResponse = $this->getJson('/api/v1/face/options/categories');
        $categoriesResponse->assertOk();

        // Niches endpoint should work without authentication
        $nichesResponse = $this->getJson('/api/v1/face/options/niches');
        $nichesResponse->assertOk();
    }

    public function test_can_update_all_categorie_values(): void
    {
        $categories = ['acteur', 'influenceur', 'createur', 'mannequin', 'figurant'];

        foreach ($categories as $categorie) {
            $response = $this->actingAs($this->faceUser)
                ->putJson('/api/v1/face/category-niche', [
                    'categorie' => $categorie,
                ]);

            $response->assertOk()
                ->assertJsonPath('data.categorie', $categorie);
        }
    }

    public function test_can_update_all_niche_values(): void
    {
        $niches = ['beaute', 'nourriture', 'decouverte', 'mode'];

        foreach ($niches as $niche) {
            $response = $this->actingAs($this->faceUser)
                ->putJson('/api/v1/face/category-niche', [
                    'niche' => $niche,
                ]);

            $response->assertOk()
                ->assertJsonPath('data.niche', $niche);
        }
    }
}
