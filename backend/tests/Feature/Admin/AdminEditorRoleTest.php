<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditorRoleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $editor;

    private Admin $admin;
    private string $editorToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = Admin::factory()->editor()->create();
        $this->admin = Admin::factory()->create(); // default role = admin
        $this->editorToken = $this->editor->createToken('editor-token')->plainTextToken;
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    // ─── EDITOR CAN ACCESS ARTICLES ──────────────────────────────

    public function test_editor_can_list_articles(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk();
    }

    public function test_editor_can_create_article(): void
    {
        $response = $this->withToken($this->editorToken)
            ->postJson('/api/v1/admin/articles', [
                'title' => 'Article de test éditeur',
                'content' => 'Contenu de test pour vérifier les droits éditeur. Ce contenu doit faire au moins cinquante caractères.',
                'category' => 'actualites',
                'status' => 'draft',
            ]);

        $response->assertStatus(201);
    }

    public function test_editor_can_show_article(): void
    {
        $article = Article::factory()->create(['admin_id' => $this->editor->id]);

        $response = $this->withToken($this->editorToken)
            ->getJson("/api/v1/admin/articles/{$article->uuid}");

        $response->assertOk();
    }

    public function test_editor_can_update_article(): void
    {
        $article = Article::factory()->create(['admin_id' => $this->editor->id]);

        $response = $this->withToken($this->editorToken)
            ->putJson("/api/v1/admin/articles/{$article->uuid}", [
                'title' => 'Titre modifié par éditeur',
                'content' => $article->content,
                'category' => $article->category->value,
                'status' => $article->status->value,
            ]);

        $response->assertOk();
    }

    public function test_editor_can_delete_article(): void
    {
        $article = Article::factory()->create(['admin_id' => $this->editor->id]);

        $response = $this->withToken($this->editorToken)
            ->deleteJson("/api/v1/admin/articles/{$article->uuid}");

        $response->assertOk();
    }

    // ─── EDITOR BLOCKED FROM DASHBOARD ───────────────────────────

    public function test_editor_cannot_access_dashboard_stats(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertForbidden();
    }

    public function test_editor_cannot_access_dashboard_trends(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/dashboard/trends');

        $response->assertForbidden();
    }

    public function test_editor_cannot_access_dashboard_recent_activity(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/dashboard/recent-activity');

        $response->assertForbidden();
    }

    // ─── EDITOR BLOCKED FROM FACES ───────────────────────────────

    public function test_editor_cannot_access_faces_list(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/faces');

        $response->assertForbidden();
    }

    // ─── EDITOR BLOCKED FROM PRODUCERS ───────────────────────────

    public function test_editor_cannot_access_producers_list(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/producers');

        $response->assertForbidden();
    }

    // ─── EDITOR BLOCKED FROM MISSIONS ────────────────────────────

    public function test_editor_cannot_access_missions_list(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/missions');

        $response->assertForbidden();
    }

    public function test_editor_cannot_access_withdrawal_requests(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/finance/withdrawal-requests');

        $response->assertForbidden();
    }

    // ─── EDITOR BLOCKED FROM ADMIN MANAGEMENT ────────────────────

    public function test_editor_cannot_access_admin_management(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/admins');

        $response->assertForbidden();
    }

    // ─── ADMIN (NON-EDITOR) CAN STILL ACCESS EVERYTHING ─────────

    public function test_admin_can_access_dashboard_stats(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/dashboard/stats');

        $response->assertOk();
    }

    public function test_admin_can_access_faces_list(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces');

        $response->assertOk();
    }

    public function test_admin_can_access_producers_list(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/producers');

        $response->assertOk();
    }

    public function test_admin_can_access_missions_list(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/missions');

        $response->assertOk();
    }

    public function test_admin_can_access_articles_list(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/articles');

        $response->assertOk();
    }

    // ─── EDITOR CAN STILL USE AUTH ROUTES ────────────────────────

    public function test_editor_can_access_me_endpoint(): void
    {
        $response = $this->withToken($this->editorToken)
            ->getJson('/api/v1/admin/me');

        $response->assertOk();
    }
}
