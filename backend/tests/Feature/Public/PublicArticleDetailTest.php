<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\ArticleCategory;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicArticleDetailTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create(['name' => 'Jean Admin']);
    }

    private function createPublishedArticle(array $attributes = []): Article
    {
        return Article::factory()
            ->for($this->admin)
            ->published()
            ->create($attributes);
    }

    // ─── 4.1 Successful retrieval of published article with all fields ──

    public function test_returns_published_article_with_all_expected_fields(): void
    {
        $article = $this->createPublishedArticle([
            'title' => 'Conseils pour votre premier casting',
            'content' => '<h2>Introduction</h2><p>Full rich text article body...</p>',
            'excerpt' => 'Découvrez les meilleures pratiques...',
            'category' => ArticleCategory::ConseilsFace->value,
        ]);

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                    'excerpt',
                    'category' => ['value', 'label'],
                    'featured_image',
                    'published_at',
                    'created_at',
                    'author_name',
                ],
                'message',
            ]);

        $data = $response->json('data');
        $this->assertEquals($article->uuid, $data['id']);
        $this->assertEquals('Conseils pour votre premier casting', $data['title']);
        $this->assertEquals($article->slug, $data['slug']);
        $this->assertEquals('Découvrez les meilleures pratiques...', $data['excerpt']);
        $this->assertEquals('Jean Admin', $data['author_name']);
    }

    // ─── 4.2 Content field is present and contains full article content ──

    public function test_content_field_is_present_and_contains_full_article_content(): void
    {
        $fullContent = '<h2>Introduction</h2><p>This is a long article with <strong>rich text</strong> formatting.</p><ul><li>Item 1</li><li>Item 2</li></ul>';

        $article = $this->createPublishedArticle([
            'content' => $fullContent,
        ]);

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();
        $this->assertEquals($fullContent, $response->json('data.content'));
    }

    // ─── 4.3 404 for non-existent slug ──────────────────────────────────

    public function test_returns_404_for_non_existent_slug(): void
    {
        $response = $this->getJson('/api/v1/public/articles/nonexistent-slug');

        $response->assertNotFound()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                ],
            ])
            ->assertJson([
                'error' => [
                    'code' => 'ARTICLE_NOT_FOUND',
                    'message' => 'Article non trouvé',
                ],
            ]);
    }

    // ─── 4.4 404 for draft article ──────────────────────────────────────

    public function test_returns_404_for_draft_article(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertNotFound()
            ->assertJson([
                'error' => [
                    'code' => 'ARTICLE_NOT_FOUND',
                ],
            ]);
    }

    // ─── 4.5 Response structure: no meta key, has data and message ──────

    public function test_response_has_no_meta_key(): void
    {
        $article = $this->createPublishedArticle();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();
        $this->assertArrayNotHasKey('meta', $response->json());
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('message', $response->json());
    }

    // ─── 4.6 Response excludes status, updated_at, and admin object ─────

    public function test_response_excludes_status_updated_at_and_admin_object(): void
    {
        $article = $this->createPublishedArticle();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();

        $data = $response->json('data');

        $this->assertArrayNotHasKey('status', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        $this->assertArrayNotHasKey('admin', $data);
        $this->assertArrayNotHasKey('admin_id', $data);
    }

    // ─── 4.7 Author name is returned as string ──────────────────────────

    public function test_author_name_is_returned_as_string(): void
    {
        $article = $this->createPublishedArticle();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();
        $this->assertEquals('Jean Admin', $response->json('data.author_name'));
        $this->assertIsString($response->json('data.author_name'));
    }

    // ─── 4.8 404 error response format ──────────────────────────────────

    public function test_404_error_response_format(): void
    {
        $response = $this->getJson('/api/v1/public/articles/does-not-exist');

        $response->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'ARTICLE_NOT_FOUND',
                    'message' => 'Article non trouvé',
                ],
            ]);
    }

    // ─── 4.9 No authentication required ─────────────────────────────────

    public function test_does_not_require_authentication(): void
    {
        $article = $this->createPublishedArticle();

        // No auth token, no actingAs
        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();
    }

    // ─── 4.10 Rate limiting ─────────────────────────────────────────────

    public function test_rate_limiting_is_applied(): void
    {
        $article = $this->createPublishedArticle();

        // Make 60 requests (limit is 60/min)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson("/api/v1/public/articles/{$article->slug}");
            $response->assertOk();
        }

        // 61st request should be rate limited
        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");
        $response->assertStatus(429);
    }

    // ─── 4.11 Success message is returned ───────────────────────────────

    public function test_success_message_is_returned(): void
    {
        $article = $this->createPublishedArticle();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();
        $this->assertEquals('Article retrieved successfully', $response->json('message'));
    }

    // ─── 4.12 Category value/label format in detail response ────────────

    public function test_category_value_and_label_format(): void
    {
        $article = $this->createPublishedArticle([
            'category' => ArticleCategory::GuideProducteur->value,
        ]);

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();

        $categoryData = $response->json('data.category');
        $this->assertEquals('guide-producteur', $categoryData['value']);
        $this->assertEquals('Guide Producteur', $categoryData['label']);
    }

    // ─── 4.14 Date fields are in ISO 8601 format ──────────────────────

    public function test_date_fields_are_in_iso_8601_format(): void
    {
        $article = $this->createPublishedArticle();

        $response = $this->getJson("/api/v1/public/articles/{$article->slug}");

        $response->assertOk();

        $data = $response->json('data');

        // ISO 8601 format: 2026-01-15T10:00:00+00:00 or similar
        $iso8601Pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/';

        $this->assertMatchesRegularExpression($iso8601Pattern, $data['published_at']);
        $this->assertMatchesRegularExpression($iso8601Pattern, $data['created_at']);
    }

    // ─── 4.13 Featured image URL returned correctly ─────────────────────

    public function test_featured_image_url_returned_correctly(): void
    {
        // Article WITH featured image
        $articleWithImage = $this->createPublishedArticle([
            'featured_image' => 'test-image.jpg',
        ]);

        $response = $this->getJson("/api/v1/public/articles/{$articleWithImage->slug}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.featured_image'));
        $this->assertStringContainsString('test-image.jpg', $response->json('data.featured_image'));

        // Article WITHOUT featured image
        $articleWithoutImage = $this->createPublishedArticle([
            'featured_image' => null,
        ]);

        $response = $this->getJson("/api/v1/public/articles/{$articleWithoutImage->slug}");

        $response->assertOk();
        $this->assertNull($response->json('data.featured_image'));
    }
}
