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

class AdminCreateArticleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    private string $endpoint = '/api/v1/admin/articles';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    private function validArticleData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Conseils pour réussir son casting',
            'content' => str_repeat('Contenu de qualité pour un article complet. ', 5),
            'category' => ArticleCategory::ConseilsFace->value,
        ], $overrides);
    }

    // ──────────────────────────────────────────────
    // 6.1 Happy path: admin creates article → 201
    // ──────────────────────────────────────────────

    public function test_admin_creates_article_returns_201_with_correct_structure(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData());

        $response->assertStatus(201)
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
            ->assertJsonPath('data.title', 'Conseils pour réussir son casting')
            ->assertJsonPath('data.category.value', 'conseils-face')
            ->assertJsonPath('data.category.label', 'Conseils Face')
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.status.label', 'Brouillon')
            ->assertJsonPath('data.admin.id', $this->admin->uuid)
            ->assertJsonPath('message', 'Article créé avec succès');

        $this->assertNotEmpty($response->json('data.slug'));
        $this->assertDatabaseHas('articles', [
            'title' => 'Conseils pour réussir son casting',
            'admin_id' => $this->admin->id,
        ]);
    }

    public function test_slug_is_auto_generated_from_title(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'title' => 'Mon Premier Article de Blog',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'mon-premier-article-de-blog');
    }

    public function test_article_with_excerpt_stores_and_returns_it(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'excerpt' => 'Un résumé court de cet article.',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.excerpt', 'Un résumé court de cet article.');

        $this->assertDatabaseHas('articles', [
            'excerpt' => 'Un résumé court de cet article.',
        ]);
    }

    // ──────────────────────────────────────────────
    // 6.2 Featured image upload
    // ──────────────────────────────────────────────

    public function test_article_with_featured_image_upload(): void
    {
        $image = UploadedFile::fake()->image('featured.jpg', 800, 600)->size(1024);

        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, array_merge($this->validArticleData(), [
                'featured_image' => $image,
            ]));

        $response->assertStatus(201);

        $featuredImageUrl = $response->json('data.featured_image');
        $this->assertNotNull($featuredImageUrl);

        $article = Article::first();
        Storage::disk('public')->assertExists('articles/featured/'.$article->featured_image);
    }

    // ──────────────────────────────────────────────
    // 6.3 Published status auto-sets published_at
    // ──────────────────────────────────────────────

    public function test_published_status_auto_sets_published_at(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'status' => ArticleStatus::Published->value,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'published');

        $this->assertNotNull($response->json('data.published_at'));
    }

    // ──────────────────────────────────────────────
    // 6.4 Draft status leaves published_at null
    // ──────────────────────────────────────────────

    public function test_draft_status_leaves_published_at_null(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'status' => ArticleStatus::Draft->value,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.published_at', null);
    }

    public function test_default_status_is_draft_when_omitted(): void
    {
        $data = $this->validArticleData();
        unset($data['status']);

        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.published_at', null);
    }

    // ──────────────────────────────────────────────
    // 6.5 Validation errors
    // ──────────────────────────────────────────────

    public function test_missing_title_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'title' => '',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.title.0', 'Le titre est obligatoire.');
    }

    public function test_missing_content_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'content' => '',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_content_too_short_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'content' => 'Too short',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_missing_category_returns_422(): void
    {
        $data = $this->validArticleData();
        unset($data['category']);

        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $data);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.category.0', 'La catégorie est obligatoire.');
    }

    public function test_invalid_category_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'category' => 'invalid-category',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.category.0', 'La catégorie sélectionnée est invalide.');
    }

    public function test_invalid_status_returns_422(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData([
                'status' => 'invalid-status',
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.status.0', 'Le statut sélectionné est invalide.');
    }

    // ──────────────────────────────────────────────
    // 6.6 Auth: no token → 401, user → 403, admin → 201
    // ──────────────────────────────────────────────

    public function test_no_token_returns_401(): void
    {
        $response = $this->postJson($this->endpoint, $this->validArticleData());

        $response->assertStatus(401);
    }

    public function test_regular_user_token_returns_403(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('user-token')->plainTextToken;

        $response = $this->withToken($userToken)
            ->postJson($this->endpoint, $this->validArticleData());

        $response->assertStatus(403);
    }

    public function test_admin_token_returns_201(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, $this->validArticleData());

        $response->assertStatus(201);
    }

    // ──────────────────────────────────────────────
    // 6.7 Featured image validation
    // ──────────────────────────────────────────────

    public function test_wrong_image_mime_type_returns_422(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, array_merge($this->validArticleData(), [
                'featured_image' => $file,
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_image_too_large_returns_422(): void
    {
        $image = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->withToken($this->adminToken)
            ->postJson($this->endpoint, array_merge($this->validArticleData(), [
                'featured_image' => $image,
            ]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
