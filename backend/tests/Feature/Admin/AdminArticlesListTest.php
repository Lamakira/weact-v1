<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArticlesListTest extends TestCase
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

    public function test_admin_can_list_articles(): void
    {
        Article::factory()->count(3)->create(['admin_id' => $this->admin->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug', 'category', 'status', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'message',
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_articles_list_includes_both_draft_and_published(): void
    {
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => ArticleStatus::Draft,
        ]);
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => ArticleStatus::Published,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_articles_list_is_paginated(): void
    {
        Article::factory()->count(20)->create(['admin_id' => $this->admin->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(15, 'data');
    }

    public function test_admin_can_search_articles_by_title(): void
    {
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'title' => 'Guide pour les faces débutantes',
        ]);
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'title' => 'Actualités du mois',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles?search=débutantes');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Guide pour les faces débutantes');
    }

    public function test_admin_can_filter_articles_by_category(): void
    {
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'category' => ArticleCategory::ConseilsFace,
        ]);
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'category' => ArticleCategory::Actualites,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles?category=conseils-face');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_filter_articles_by_status(): void
    {
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => ArticleStatus::Draft,
        ]);
        Article::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => ArticleStatus::Published,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles?status=draft');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'draft');
    }

    public function test_admin_articles_list_ordered_by_created_at_desc(): void
    {
        $older = Article::factory()->create([
            'admin_id' => $this->admin->id,
            'title' => 'Older Article',
            'created_at' => now()->subDays(2),
        ]);
        $newer = Article::factory()->create([
            'admin_id' => $this->admin->id,
            'title' => 'Newer Article',
            'created_at' => now(),
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Newer Article', $data[0]['title']);
        $this->assertEquals('Older Article', $data[1]['title']);
    }

    public function test_unauthenticated_user_cannot_list_admin_articles(): void
    {
        $response = $this->getJson('/api/v1/admin/articles');

        $response->assertUnauthorized();
    }

    public function test_admin_articles_list_includes_admin_relationship(): void
    {
        Article::factory()->create(['admin_id' => $this->admin->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['admin' => ['id', 'name']],
                ],
            ]);
    }
}
