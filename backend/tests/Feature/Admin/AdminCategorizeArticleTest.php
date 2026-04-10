<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ArticleCategory;
use App\Models\Admin;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategorizeArticleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    private function endpoint(Article $article): string
    {
        return "/api/v1/admin/articles/{$article->uuid}/category";
    }

    // ──────────────────────────────────────────────
    // 5.1 Happy path: admin updates article category → 200
    // ──────────────────────────────────────────────

    public function test_admin_updates_article_category_returns_200(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->inCategory(ArticleCategory::ConseilsFace)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::GuideProducteur->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                    'excerpt',
                    'category' => ['value', 'label'],
                    'status' => ['value', 'label'],
                    'featured_image',
                    'published_at',
                    'created_at',
                    'updated_at',
                    'admin' => ['id', 'name', 'email'],
                ],
                'message',
                'meta',
            ])
            ->assertJsonPath('data.category.value', 'guide-producteur')
            ->assertJsonPath('data.category.label', 'Guide Producteur')
            ->assertJsonPath('message', "Catégorie de l'article mise à jour avec succès");

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'category' => 'guide-producteur',
        ]);
    }

    // ──────────────────────────────────────────────
    // 5.2 All 3 categories are valid
    // ──────────────────────────────────────────────

    public function test_update_to_conseils_face_category(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->inCategory(ArticleCategory::Actualites)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::ConseilsFace->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.category.value', 'conseils-face')
            ->assertJsonPath('data.category.label', 'Conseils Face');
    }

    public function test_update_to_guide_producteur_category(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->inCategory(ArticleCategory::ConseilsFace)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::GuideProducteur->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.category.value', 'guide-producteur')
            ->assertJsonPath('data.category.label', 'Guide Producteur');
    }

    public function test_update_to_actualites_category(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->inCategory(ArticleCategory::ConseilsFace)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::Actualites->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.category.value', 'actualites')
            ->assertJsonPath('data.category.label', 'Actualités');
    }

    // ──────────────────────────────────────────────
    // 5.3 Invalid category → 422
    // ──────────────────────────────────────────────

    public function test_invalid_category_returns_422(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => 'invalid-category',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.category.0', 'La catégorie sélectionnée est invalide.');
    }

    public function test_missing_category_returns_422(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error')
            ->assertJsonPath('error.details.category.0', 'La catégorie est obligatoire.');
    }

    // ──────────────────────────────────────────────
    // 5.4 Non-existent article → 404
    // ──────────────────────────────────────────────

    public function test_nonexistent_article_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/v1/admin/articles/00000000-0000-0000-0000-000000000000/category', [
                'category' => ArticleCategory::ConseilsFace->value,
            ]);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // 5.5 Auth: no token → 401, user → 403, admin → 200
    // ──────────────────────────────────────────────

    public function test_no_token_returns_401(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->patchJson($this->endpoint($article), [
            'category' => ArticleCategory::ConseilsFace->value,
        ]);

        $response->assertStatus(401);
    }

    public function test_regular_user_token_returns_403(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('user-token')->plainTextToken;

        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($userToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::ConseilsFace->value,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_token_returns_200(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'category' => ArticleCategory::GuideProducteur->value,
            ]);

        $response->assertStatus(200);
    }
}
