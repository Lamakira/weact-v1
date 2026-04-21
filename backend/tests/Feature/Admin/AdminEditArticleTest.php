<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminEditArticleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    private function endpoint(Article $article): string
    {
        return "/api/v1/admin/articles/{$article->uuid}";
    }

    // ──────────────────────────────────────────────
    // 5.1 Full update: all fields provided → 200
    // ──────────────────────────────────────────────

    public function test_full_update_all_fields_returns_200(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->inCategory(ArticleCategory::ConseilsFace)
            ->create([
                'title' => 'Original Title',
                'content' => str_repeat('Original content for the article. ', 5),
                'excerpt' => 'Original excerpt.',
            ]);

        $newContent = trim(str_repeat('Updated content for the article body. ', 5));

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'title' => 'Updated Title',
                'content' => $newContent,
                'excerpt' => 'Updated excerpt.',
                'category' => ArticleCategory::GuideProducteur->value,
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
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.content', $newContent)
            ->assertJsonPath('data.excerpt', 'Updated excerpt.')
            ->assertJsonPath('data.category.value', 'guide-producteur')
            ->assertJsonPath('data.category.label', 'Guide Producteur')
            ->assertJsonPath('data.status.value', 'published')
            ->assertJsonPath('data.status.label', 'Publié')
            ->assertJsonPath('message', 'Article mis à jour avec succès');

        $this->assertNotNull($response->json('data.published_at'));
    }

    // ──────────────────────────────────────────────
    // 5.2 Partial update: only title → other fields preserved
    // ──────────────────────────────────────────────

    public function test_partial_update_only_title_preserves_other_fields(): void
    {
        $originalContent = trim(str_repeat('Original content preserved. ', 5));

        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->inCategory(ArticleCategory::Actualites)
            ->create([
                'title' => 'Original Title',
                'content' => $originalContent,
                'excerpt' => 'Original excerpt.',
            ]);

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'title' => 'New Title Only',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'New Title Only')
            ->assertJsonPath('data.content', $originalContent)
            ->assertJsonPath('data.excerpt', 'Original excerpt.')
            ->assertJsonPath('data.category.value', 'actualites')
            ->assertJsonPath('data.status.value', 'draft');
    }

    // ──────────────────────────────────────────────
    // 5.3 Partial update: only content
    // ──────────────────────────────────────────────

    public function test_partial_update_only_content(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create(['title' => 'Unchanged Title']);

        $newContent = trim(str_repeat('Brand new content for article. ', 5));

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'content' => $newContent,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.content', $newContent)
            ->assertJsonPath('data.title', 'Unchanged Title');
    }

    // ──────────────────────────────────────────────
    // 5.4 Title change regenerates slug
    // ──────────────────────────────────────────────

    public function test_title_change_regenerates_slug(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create(['title' => 'Original Title']);

        $originalSlug = $article->slug;

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'title' => 'Completely New Title',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'completely-new-title');

        $this->assertNotEquals($originalSlug, $response->json('data.slug'));
    }

    // ──────────────────────────────────────────────
    // 5.5 Title unchanged preserves slug
    // ──────────────────────────────────────────────

    public function test_title_unchanged_preserves_slug(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create(['title' => 'My Stable Title']);

        $originalSlug = $article->slug;

        $newContent = trim(str_repeat('Different content but same title. ', 5));

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'content' => $newContent,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', $originalSlug);
    }

    // ──────────────────────────────────────────────
    // 5.6 Status change to published sets published_at
    // ──────────────────────────────────────────────

    public function test_status_change_to_published_sets_published_at(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create();

        $this->assertNull($article->published_at);

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'published');

        $this->assertNotNull($response->json('data.published_at'));

        $article->refresh();
        $this->assertNotNull($article->published_at);
    }

    // ──────────────────────────────────────────────
    // 5.7 Status change to draft clears published_at
    // ──────────────────────────────────────────────

    public function test_status_change_to_draft_clears_published_at(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $this->assertNotNull($article->published_at);

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'status' => ArticleStatus::Draft->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.published_at', null);

        $article->refresh();
        $this->assertNull($article->published_at);
    }

    // ──────────────────────────────────────────────
    // 5.8 Featured image replacement
    // ──────────────────────────────────────────────

    public function test_featured_image_replacement_deletes_old_and_stores_new(): void
    {
        // Create article with initial image
        $oldImage = UploadedFile::fake()->image('old.jpg', 400, 300)->size(512);
        Storage::disk('public')->putFileAs('articles/featured', $oldImage, 'old-uuid.jpg');

        $article = Article::factory()
            ->for($this->admin)
            ->create(['featured_image' => 'old-uuid.jpg']);

        Storage::disk('public')->assertExists('articles/featured/old-uuid.jpg');

        // Upload replacement image
        $newImage = UploadedFile::fake()->image('new.jpg', 800, 600)->size(1024);

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'featured_image' => $newImage,
            ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.featured_image'));

        // Old image deleted
        Storage::disk('public')->assertMissing('articles/featured/old-uuid.jpg');

        // New image exists
        $article->refresh();
        Storage::disk('public')->assertExists('articles/featured/'.$article->featured_image);
    }

    // ──────────────────────────────────────────────
    // Empty body → 200, article unchanged (no-op)
    // ──────────────────────────────────────────────

    public function test_empty_body_returns_200_with_article_unchanged(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create(['title' => 'Untouched Title']);

        $originalUpdatedAt = $article->updated_at->toISOString();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), []);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Untouched Title')
            ->assertJsonPath('data.status.value', 'draft');
    }

    // ──────────────────────────────────────────────
    // Status idempotency: same status → published_at unchanged
    // ──────────────────────────────────────────────

    public function test_same_status_does_not_reset_published_at(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $originalPublishedAt = $article->published_at->toISOString();

        $this->travel(5)->minutes();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'status' => ArticleStatus::Published->value,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'published');

        $article->refresh();
        $this->assertEquals($originalPublishedAt, $article->published_at->toISOString());
    }

    // ──────────────────────────────────────────────
    // 5.9 Validation errors
    // ──────────────────────────────────────────────

    public function test_empty_title_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'title' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.title.0', 'Le titre est obligatoire.');
    }

    public function test_content_too_short_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'content' => 'Too short',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.content.0', 'Le contenu doit contenir au moins 50 caractères.');
    }

    public function test_invalid_category_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'category' => 'invalid-category',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.category.0', 'La catégorie sélectionnée est invalide.');
    }

    public function test_invalid_status_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'status' => 'invalid-status',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.status.0', 'Le statut sélectionné est invalide.');
    }

    public function test_wrong_image_type_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'featured_image' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_image_too_large_returns_422(): void
    {
        $article = Article::factory()->for($this->admin)->create();
        $image = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'featured_image' => $image,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // ──────────────────────────────────────────────
    // 5.10 Non-existent article → 404
    // ──────────────────────────────────────────────

    public function test_nonexistent_article_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/articles/00000000-0000-0000-0000-000000000000', [
                'title' => 'Does Not Matter',
            ]);

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // 5.11 Auth: no token → 401, user → 403, admin → 200
    // ──────────────────────────────────────────────

    public function test_no_token_returns_401(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->putJson($this->endpoint($article), [
            'title' => 'Updated',
        ]);

        $response->assertStatus(401);
    }

    public function test_regular_user_token_returns_403(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('user-token')->plainTextToken;

        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($userToken)
            ->putJson($this->endpoint($article), [
                'title' => 'Updated',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_token_returns_200(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $newContent = trim(str_repeat('Valid content for the update. ', 5));

        $response = $this->withToken($this->adminToken)
            ->putJson($this->endpoint($article), [
                'content' => $newContent,
            ]);

        $response->assertStatus(200);
    }
}
