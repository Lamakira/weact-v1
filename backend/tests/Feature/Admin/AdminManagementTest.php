<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Face;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::factory()->superAdmin()->create();
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_returns_single_admin_with_role(): void
    {
        $admin = Admin::factory()->create(['name' => 'Jean Admin', 'email' => 'jean@weact.bj']);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/admins/{$admin->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'created_at'],
                'message',
            ])
            ->assertJsonPath('data.name', 'Jean Admin')
            ->assertJsonPath('data.email', 'jean@weact.bj')
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_show_returns_404_for_nonexistent_admin(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/admins/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_update_admin_name_successfully(): void
    {
        $admin = Admin::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin->uuid}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('message', 'Administrateur mis à jour avec succès');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_admin_email_successfully(): void
    {
        $admin = Admin::factory()->create(['email' => 'old@weact.bj']);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin->uuid}", [
                'email' => 'new@weact.bj',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'new@weact.bj');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'email' => 'new@weact.bj',
        ]);
    }

    public function test_update_admin_role_successfully(): void
    {
        $admin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin->uuid}", [
                'role' => 'editor',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'role' => 'editor',
        ]);
    }

    public function test_update_with_duplicate_email_returns_422(): void
    {
        $admin1 = Admin::factory()->create(['email' => 'taken@weact.bj']);
        $admin2 = Admin::factory()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin2->uuid}", [
                'email' => 'taken@weact.bj',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.email.0', 'Cet email est déjà utilisé.');
    }

    public function test_update_with_invalid_role_returns_422(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin->uuid}", [
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
    }

    public function test_self_demotion_prevention(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$this->superAdmin->uuid}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'self_demotion')
            ->assertJsonPath('error.message', 'Impossible de modifier votre propre rôle de super-administrateur.');

        $this->assertDatabaseHas('admins', [
            'id' => $this->superAdmin->id,
            'role' => 'superadmin',
        ]);
    }

    public function test_update_ignores_password_field(): void
    {
        $admin = Admin::factory()->create(['password' => 'OriginalPassword1!']);
        $originalHash = $admin->password;

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$admin->uuid}", [
                'name' => 'Updated Name',
                'password' => 'HackedPassword1!',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $admin->refresh();
        $this->assertEquals($originalHash, $admin->password);
    }

    public function test_superadmin_can_update_own_name_without_role_change(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$this->superAdmin->uuid}", [
                'name' => 'Updated SuperAdmin Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated SuperAdmin Name');
    }

    // ─── DESTROY ──────────────────────────────────────────────────

    public function test_delete_admin_successfully(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/admins/{$admin->uuid}");

        $response->assertOk()
            ->assertJsonPath('message', 'Administrateur supprimé avec succès');

        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
    }

    public function test_delete_self_prevention(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/admins/{$this->superAdmin->uuid}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'self_deletion')
            ->assertJsonPath('error.message', 'Impossible de supprimer votre propre compte administrateur.');

        $this->assertDatabaseHas('admins', ['id' => $this->superAdmin->id]);
    }

    public function test_delete_revokes_admin_tokens(): void
    {
        $admin = Admin::factory()->create();
        $admin->createToken('test-token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Admin::class,
            'tokenable_id' => $admin->id,
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/admins/{$admin->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => Admin::class,
            'tokenable_id' => $admin->id,
        ]);
    }

    // ─── SUPERADMIN MIDDLEWARE (403 for non-superadmin) ───────────

    public function test_non_superadmin_gets_403_on_index(): void
    {
        $regularAdmin = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertForbidden();
    }

    public function test_non_superadmin_gets_403_on_store(): void
    {
        $regularAdmin = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test',
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertForbidden();
    }

    public function test_non_superadmin_gets_403_on_show(): void
    {
        $regularAdmin = Admin::factory()->create();
        $target = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->getJson("/api/v1/admin/admins/{$target->uuid}");

        $response->assertForbidden();
    }

    public function test_non_superadmin_gets_403_on_update(): void
    {
        $regularAdmin = Admin::factory()->create();
        $target = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->putJson("/api/v1/admin/admins/{$target->uuid}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_non_superadmin_gets_403_on_delete(): void
    {
        $regularAdmin = Admin::factory()->create();
        $target = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/admins/{$target->uuid}");

        $response->assertForbidden();
    }

    public function test_editor_gets_403_on_admin_routes(): void
    {
        $editor = Admin::factory()->editor()->create();

        $response = $this->actingAs($editor, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertForbidden();
    }

    // ─── AUTH GUARDS ──────────────────────────────────────────────

    public function test_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/admin/admins/00000000-0000-0000-0000-000000000000');

        $response->assertUnauthorized();
    }

    public function test_returns_403_for_non_admin_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertForbidden();
    }

    // ─── /admin/me INCLUDES ROLE ──────────────────────────────────

    public function test_me_endpoint_includes_role_field(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/me');

        $response->assertOk()
            ->assertJsonPath('data.role', 'superadmin');
    }
}
