<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSetArticleStatusTest extends TestCase
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
        return "/api/v1/admin/articles/{$article->uuid}/status";
    }

    // ──────────────────────────────────────────────
    // 5.1 Publish transition: draft → published, published_at set
    // ──────────────────────────────────────────────

    public function test_publish_draft_article_returns_200_with_published_at(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create();

        $this->assertNull($article->published_at);

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => ArticleStatus::Published->value,
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
            ->assertJsonPath('data.status.value', 'published')
            ->assertJsonPath('data.status.label', 'Publié')
            ->assertJsonPath('message', "Statut de l'article mis à jour avec succès");

        $this->assertNotNull($response->json('data.published_at'));

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'published',
        ]);

        $article->refresh();
        $this->assertNotNull($article->published_at);
    }

    // ──────────────────────────────────────────────
    // 5.2 Unpublish transition: published → draft, published_at cleared
    // ──────────────────────────────────────────────

    public function test_unpublish_article_returns_200_with_published_at_null(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $this->assertNotNull($article->published_at);

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => ArticleStatus::Draft->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.status.label', 'Brouillon')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'status' => 'draft',
        ]);

        $article->refresh();
        $this->assertNull($article->published_at);
    }

    // ──────────────────────────────────────────────
    // 5.3 Both status values are valid
    // ──────────────────────────────────────────────

    public function test_set_status_to_draft(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => ArticleStatus::Draft->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'draft');
    }

    public function test_set_status_to_published(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'published');
    }

    // ──────────────────────────────────────────────
    // Idempotency: same status → 200, no side effect
    // ──────────────────────────────────────────────

    public function test_setting_same_status_returns_200_without_changing_published_at(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $originalPublishedAt = $article->published_at->toISOString();

        $this->travel(5)->minutes();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'published');

        $article->refresh();
        $this->assertEquals($originalPublishedAt, $article->published_at->toISOString());
    }

    // ──────────────────────────────────────────────
    // 5.4 Invalid status → 422
    // ──────────────────────────────────────────────

    public function test_invalid_status_returns_422(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), [
                'status' => 'invalid-status',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.status.0', 'Le statut sélectionné est invalide.');
    }

    // ──────────────────────────────────────────────
    // 5.5 Missing status field → 422
    // ──────────────────────────────────────────────

    public function test_missing_status_returns_422(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->patchJson($this->endpoint($article), []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.status.0', 'Le statut est obligatoire.');
    }

    // ──────────────────────────────────────────────
    // 5.6 Non-existent article → 404
    // ──────────────────────────────────────────────

    public function test_nonexistent_article_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->patchJson('/api/v1/admin/articles/00000000-0000-0000-0000-000000000000/status', [
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // 5.7 Auth: no token → 401, user → 403, admin → 200
    // ──────────────────────────────────────────────

    public function test_no_token_returns_401(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->patchJson($this->endpoint($article), [
            'status' => ArticleStatus::Published->value,
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
                'status' => ArticleStatus::Published->value,
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
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(200);
    }
}
