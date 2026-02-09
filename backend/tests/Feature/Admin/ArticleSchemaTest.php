<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleSchemaTest extends TestCase
{
    use RefreshDatabase;

    // --- Migration Tests ---

    public function test_articles_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('articles'));
    }

    public function test_articles_table_has_all_required_columns(): void
    {
        $expectedColumns = [
            'id',
            'admin_id',
            'title',
            'slug',
            'content',
            'excerpt',
            'category',
            'status',
            'featured_image',
            'published_at',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('articles', $column),
                "Column '{$column}' is missing from articles table"
            );
        }
    }

    public function test_slug_column_has_unique_index(): void
    {
        $admin = Admin::factory()->create();

        $article1 = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => 'Unique Title',
        ]);

        // Direct slug collision should fail at DB level
        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('articles')->insert([
            'admin_id' => $admin->id,
            'title' => 'Different Title',
            'slug' => $article1->slug,
            'content' => 'Some content',
            'category' => 'actualites',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- Model Relationship Tests ---

    public function test_article_belongs_to_admin(): void
    {
        $admin = Admin::factory()->create();
        $article = Article::factory()->create(['admin_id' => $admin->id]);

        $this->assertInstanceOf(Admin::class, $article->admin);
        $this->assertEquals($admin->id, $article->admin->id);
    }

    public function test_admin_has_many_articles(): void
    {
        $admin = Admin::factory()->create();
        Article::factory()->count(3)->create(['admin_id' => $admin->id]);

        $this->assertCount(3, $admin->articles);
        $this->assertInstanceOf(Article::class, $admin->articles->first());
    }

    public function test_deleting_admin_cascades_to_articles(): void
    {
        $admin = Admin::factory()->create();
        Article::factory()->count(2)->create(['admin_id' => $admin->id]);

        $this->assertDatabaseCount('articles', 2);

        $admin->delete();

        $this->assertDatabaseCount('articles', 0);
    }

    // --- Factory Tests ---

    public function test_article_factory_creates_valid_article(): void
    {
        $article = Article::factory()->create();

        $this->assertNotNull($article->id);
        $this->assertNotNull($article->admin_id);
        $this->assertNotEmpty($article->title);
        $this->assertNotEmpty($article->slug);
        $this->assertNotEmpty($article->content);
        $this->assertInstanceOf(ArticleCategory::class, $article->category);
        $this->assertInstanceOf(ArticleStatus::class, $article->status);
    }

    public function test_article_factory_published_state(): void
    {
        $article = Article::factory()->published()->create();

        $this->assertEquals(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_article_factory_draft_state(): void
    {
        $article = Article::factory()->draft()->create();

        $this->assertEquals(ArticleStatus::Draft, $article->status);
        $this->assertNull($article->published_at);
    }

    public function test_article_factory_in_category_state(): void
    {
        $article = Article::factory()->inCategory(ArticleCategory::ConseilsFace)->create();

        $this->assertEquals(ArticleCategory::ConseilsFace, $article->category);
    }

    // --- Model Scope Tests ---

    public function test_published_scope_filters_only_published(): void
    {
        $admin = Admin::factory()->create();
        Article::factory()->published()->count(2)->create(['admin_id' => $admin->id]);
        Article::factory()->draft()->count(3)->create(['admin_id' => $admin->id]);

        $published = Article::published()->get();

        $this->assertCount(2, $published);
        $published->each(fn (Article $a) => $this->assertEquals(ArticleStatus::Published, $a->status));
    }

    public function test_draft_scope_filters_only_drafts(): void
    {
        $admin = Admin::factory()->create();
        Article::factory()->published()->count(2)->create(['admin_id' => $admin->id]);
        Article::factory()->draft()->count(3)->create(['admin_id' => $admin->id]);

        $drafts = Article::draft()->get();

        $this->assertCount(3, $drafts);
        $drafts->each(fn (Article $a) => $this->assertEquals(ArticleStatus::Draft, $a->status));
    }

    public function test_in_category_scope_filters_by_category(): void
    {
        $admin = Admin::factory()->create();
        Article::factory()->inCategory(ArticleCategory::ConseilsFace)->count(2)->create(['admin_id' => $admin->id]);
        Article::factory()->inCategory(ArticleCategory::Actualites)->count(1)->create(['admin_id' => $admin->id]);

        $conseilsFace = Article::inCategory(ArticleCategory::ConseilsFace)->get();

        $this->assertCount(2, $conseilsFace);
    }

    // --- Slug Generation Tests ---

    public function test_slug_auto_generated_from_title(): void
    {
        $admin = Admin::factory()->create();
        $article = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => 'Mon Premier Article de Blog',
        ]);

        $this->assertEquals('mon-premier-article-de-blog', $article->slug);
    }

    public function test_slug_collision_appends_suffix(): void
    {
        $admin = Admin::factory()->create();

        $article1 = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => 'Article Test',
        ]);

        $article2 = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => 'Article Test',
        ]);

        $this->assertEquals('article-test', $article1->slug);
        $this->assertEquals('article-test-2', $article2->slug);
    }

    public function test_slug_multiple_collisions_increment_suffix(): void
    {
        $admin = Admin::factory()->create();

        $article1 = Article::factory()->create(['admin_id' => $admin->id, 'title' => 'Same Title']);
        $article2 = Article::factory()->create(['admin_id' => $admin->id, 'title' => 'Same Title']);
        $article3 = Article::factory()->create(['admin_id' => $admin->id, 'title' => 'Same Title']);

        $this->assertEquals('same-title', $article1->slug);
        $this->assertEquals('same-title-2', $article2->slug);
        $this->assertEquals('same-title-3', $article3->slug);
    }

    public function test_slug_regenerated_on_title_update(): void
    {
        $admin = Admin::factory()->create();
        $article = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => 'Original Title',
        ]);

        $this->assertEquals('original-title', $article->slug);

        $article->update(['title' => 'Updated Title']);

        $this->assertEquals('updated-title', $article->fresh()->slug);
    }

    public function test_slug_defaults_to_article_for_empty_title(): void
    {
        $admin = Admin::factory()->create();
        $article = Article::factory()->create([
            'admin_id' => $admin->id,
            'title' => '!!!',
        ]);

        $this->assertEquals('article', $article->slug);
    }

    // --- Enum Tests ---

    public function test_article_category_enum_has_correct_values(): void
    {
        $this->assertEquals(['conseils-face', 'guide-producteur', 'actualites'], ArticleCategory::values());
    }

    public function test_article_status_enum_has_correct_values(): void
    {
        $this->assertEquals(['draft', 'published'], ArticleStatus::values());
    }

    public function test_article_category_labels(): void
    {
        $this->assertEquals('Conseils Face', ArticleCategory::ConseilsFace->label());
        $this->assertEquals('Guide Producteur', ArticleCategory::GuideProducteur->label());
        $this->assertEquals('Actualités', ArticleCategory::Actualites->label());
    }

    public function test_article_status_labels(): void
    {
        $this->assertEquals('Brouillon', ArticleStatus::Draft->label());
        $this->assertEquals('Publié', ArticleStatus::Published->label());
    }

    // --- Default Status Test ---

    public function test_article_defaults_to_draft_status(): void
    {
        $admin = Admin::factory()->create();

        $article = Article::create([
            'admin_id' => $admin->id,
            'title' => 'Test Default Status',
            'content' => 'Some content here',
            'category' => 'actualites',
        ]);

        $this->assertEquals(ArticleStatus::Draft, $article->fresh()->status);
        $this->assertNull($article->fresh()->published_at);
    }
}
