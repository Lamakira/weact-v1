<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDeleteArticleTest extends TestCase
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
        return "/api/v1/admin/articles/{$article->id}";
    }

    // ──────────────────────────────────────────────
    // 4.1 Successful deletion of draft article
    // ──────────────────────────────────────────────

    public function test_can_delete_draft_article(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->draft()
            ->create();

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Article supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.2 Successful deletion of published article
    // ──────────────────────────────────────────────

    public function test_can_delete_published_article(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->published()
            ->create();

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Article supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.3 Featured image is deleted from storage
    // ──────────────────────────────────────────────

    public function test_featured_image_is_deleted_from_storage_on_article_deletion(): void
    {
        $image = UploadedFile::fake()->image('featured.jpg', 400, 300)->size(512);
        Storage::disk('public')->putFileAs('articles/featured', $image, 'test-uuid.jpg');

        $article = Article::factory()
            ->for($this->admin)
            ->create(['featured_image' => 'test-uuid.jpg']);

        Storage::disk('public')->assertExists('articles/featured/test-uuid.jpg');

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200);

        // Image file deleted
        Storage::disk('public')->assertMissing('articles/featured/test-uuid.jpg');

        // Article deleted
        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.4 Deletion without featured image succeeds
    // ──────────────────────────────────────────────

    public function test_deletion_without_featured_image_succeeds(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create(['featured_image' => null]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Article supprimé avec succès',
            ]);

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.5 Response format
    // ──────────────────────────────────────────────

    public function test_delete_response_has_correct_format(): void
    {
        $article = Article::factory()
            ->for($this->admin)
            ->create();

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200)
            ->assertJsonStructure(['message'])
            ->assertJson([
                'message' => 'Article supprimé avec succès',
            ]);

        $json = $response->json();
        $this->assertArrayNotHasKey('data', $json);
        $this->assertArrayNotHasKey('meta', $json);
    }

    // ──────────────────────────────────────────────
    // 4.6 Non-existent article → 404
    // ──────────────────────────────────────────────

    public function test_nonexistent_article_returns_404(): void
    {
        $response = $this->withToken($this->adminToken)
            ->deleteJson('/api/v1/admin/articles/99999');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────
    // 4.7 No token → 401
    // ──────────────────────────────────────────────

    public function test_no_token_returns_401(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->deleteJson($this->endpoint($article));

        $response->assertStatus(401);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.8 Regular user token → 403
    // ──────────────────────────────────────────────

    public function test_regular_user_token_returns_403(): void
    {
        $user = User::factory()->create();
        $userToken = $user->createToken('user-token')->plainTextToken;

        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($userToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(403);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
        ]);
    }

    // ──────────────────────────────────────────────
    // 4.9 Admin token → 200
    // ──────────────────────────────────────────────

    public function test_admin_token_returns_200(): void
    {
        $article = Article::factory()->for($this->admin)->create();

        $response = $this->withToken($this->adminToken)
            ->deleteJson($this->endpoint($article));

        $response->assertStatus(200);
    }
}
