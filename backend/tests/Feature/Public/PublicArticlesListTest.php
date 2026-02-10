<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\ArticleCategory;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicArticlesListTest extends TestCase
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

    // ─── 5.1 Paginated list of published articles ────────────────────

    public function test_returns_paginated_list_of_published_articles(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createPublishedArticle();
        }

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'excerpt',
                        'category' => ['value', 'label'],
                        'featured_image',
                        'published_at',
                        'created_at',
                        'author_name',
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

        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertCount(15, $response->json('data'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    // ─── 5.2 Only published articles returned ────────────────────────

    public function test_only_returns_published_articles(): void
    {
        $published = $this->createPublishedArticle(['title' => 'Published Article']);
        Article::factory()->for($this->admin)->draft()->create(['title' => 'Draft Article']);

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($published->id, $response->json('data.0.id'));
        $this->assertEquals('Published Article', $response->json('data.0.title'));
    }

    // ─── 5.3 Custom per_page parameter ───────────────────────────────

    public function test_supports_custom_per_page_parameter_with_max_30(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $this->createPublishedArticle();
        }

        // Request 30 items (max allowed)
        $response = $this->getJson('/api/v1/public/articles?per_page=30');

        $response->assertOk();
        $this->assertEquals(30, $response->json('meta.per_page'));
        $this->assertCount(30, $response->json('data'));

        // Request 50 items (exceeds max 30, returns 422)
        $response = $this->getJson('/api/v1/public/articles?per_page=50');

        $response->assertUnprocessable();
    }

    // ─── 5.4 Custom page parameter ───────────────────────────────────

    public function test_supports_page_parameter_for_pagination(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createPublishedArticle();
        }

        $response = $this->getJson('/api/v1/public/articles?page=2&per_page=15');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertCount(5, $response->json('data'));
    }

    // ─── 5.5 Ordering by published_at DESC ───────────────────────────

    public function test_articles_are_ordered_by_published_at_desc(): void
    {
        $older = $this->createPublishedArticle(['title' => 'Older Article']);
        Article::where('id', $older->id)->update(['published_at' => now()->subDays(2)]);

        $newer = $this->createPublishedArticle(['title' => 'Newer Article']);
        Article::where('id', $newer->id)->update(['published_at' => now()->subDay()]);

        $newest = $this->createPublishedArticle(['title' => 'Newest Article']);

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();
        $this->assertEquals($newest->id, $response->json('data.0.id'));
        $this->assertEquals($newer->id, $response->json('data.1.id'));
        $this->assertEquals($older->id, $response->json('data.2.id'));
    }

    // ─── 5.6 Public-safe fields only ─────────────────────────────────

    public function test_response_includes_only_public_safe_fields(): void
    {
        $this->createPublishedArticle(['content' => 'Full article content here']);

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();

        $articleData = $response->json('data.0');

        // Must include public-safe fields
        $this->assertArrayHasKey('id', $articleData);
        $this->assertArrayHasKey('title', $articleData);
        $this->assertArrayHasKey('slug', $articleData);
        $this->assertArrayHasKey('excerpt', $articleData);
        $this->assertArrayHasKey('category', $articleData);
        $this->assertArrayHasKey('featured_image', $articleData);
        $this->assertArrayHasKey('published_at', $articleData);
        $this->assertArrayHasKey('created_at', $articleData);
        $this->assertArrayHasKey('author_name', $articleData);

        // Must NOT include admin-only / heavy fields
        $this->assertArrayNotHasKey('content', $articleData);
        $this->assertArrayNotHasKey('status', $articleData);
        $this->assertArrayNotHasKey('updated_at', $articleData);
        $this->assertArrayNotHasKey('admin', $articleData);
    }

    // ─── 5.6b Category value and label are correctly formatted ──────

    public function test_response_includes_correct_category_value_and_label(): void
    {
        $this->createPublishedArticle(['category' => ArticleCategory::ConseilsFace->value]);

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();

        $categoryData = $response->json('data.0.category');

        $this->assertEquals('conseils-face', $categoryData['value']);
        $this->assertEquals('Conseils Face', $categoryData['label']);
    }

    // ─── 5.7 Author name displayed ──────────────────────────────────

    public function test_response_includes_author_name(): void
    {
        $this->createPublishedArticle();

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();
        $this->assertEquals('Jean Admin', $response->json('data.0.author_name'));
    }

    // ─── 5.8 Empty list when no published articles ───────────────────

    public function test_returns_empty_data_array_when_no_published_articles_exist(): void
    {
        // Create only draft articles
        Article::factory()->for($this->admin)->draft()->create();

        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'total' => 0,
                ],
            ]);
    }

    // ─── 5.9 Validation: invalid page ────────────────────────────────

    public function test_returns_422_for_invalid_page_parameter(): void
    {
        $response = $this->getJson('/api/v1/public/articles?page=0');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/articles?page=-1');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/articles?page=abc');
        $response->assertUnprocessable();
    }

    // ─── 5.10 Validation: invalid per_page ───────────────────────────

    public function test_returns_422_for_invalid_per_page_parameter(): void
    {
        $response = $this->getJson('/api/v1/public/articles?per_page=0');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/articles?per_page=-1');
        $response->assertUnprocessable();

        $response = $this->getJson('/api/v1/public/articles?per_page=abc');
        $response->assertUnprocessable();
    }

    // ─── 5.11 No authentication required ─────────────────────────────

    public function test_does_not_require_authentication(): void
    {
        $this->createPublishedArticle();

        // No auth token, no actingAs
        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();
    }

    // ─── 5.12 Rate limiting ──────────────────────────────────────────

    public function test_rate_limiting_is_applied(): void
    {
        $this->createPublishedArticle();

        // Make 61 requests (limit is 60/min)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/v1/public/articles');
            $response->assertOk();
        }

        $response = $this->getJson('/api/v1/public/articles');
        $response->assertStatus(429);
    }

    // ─── 5.13 Success message ────────────────────────────────────────

    public function test_success_message_is_returned(): void
    {
        $response = $this->getJson('/api/v1/public/articles');

        $response->assertOk();
        $this->assertEquals('Articles retrieved successfully', $response->json('message'));
    }
}
