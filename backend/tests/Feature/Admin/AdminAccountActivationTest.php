<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountActivationTest extends TestCase
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

    // ─── FACE TOGGLE ─────────────────────────────────────────────

    public function test_admin_can_deactivate_face_account(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v1/admin/faces/{$face->uuid}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('message', 'Compte désactivé avec succès')
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_can_reactivate_face_account(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => false,
        ]);

        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v1/admin/faces/{$face->uuid}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('message', 'Compte activé avec succès')
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_deactivating_face_revokes_tokens(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => true,
        ]);

        // Create some tokens for the user
        $user->createToken('token-1');
        $user->createToken('token-2');
        $this->assertEquals(2, $user->tokens()->count());

        $this->withToken($this->adminToken)
            ->patchJson("/api/v1/admin/faces/{$face->uuid}/toggle-active");

        $this->assertEquals(0, $user->tokens()->count());
    }

    // ─── PRODUCER TOGGLE ─────────────────────────────────────────

    public function test_admin_can_toggle_producer_account(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'is_active' => true,
        ]);

        // Deactivate
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v1/admin/producers/{$producer->uuid}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('message', 'Compte désactivé avec succès');
        $this->assertFalse($user->fresh()->is_active);

        // Reactivate
        $response = $this->withToken($this->adminToken)
            ->patchJson("/api/v1/admin/producers/{$producer->uuid}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('message', 'Compte activé avec succès');
        $this->assertTrue($user->fresh()->is_active);
    }

    // ─── LOGIN BLOCKING ──────────────────────────────────────────

    public function test_deactivated_user_cannot_login(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'deactivated@test.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'deactivated@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_DEACTIVATED')
            ->assertJsonPath('error.message', 'Votre compte a été désactivé');
    }

    public function test_reactivated_user_can_login(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'reactivated@test.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'reactivated@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    // ─── AUTH GUARDS ─────────────────────────────────────────────

    public function test_toggle_requires_admin_authentication(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->patchJson("/api/v1/admin/faces/{$face->uuid}/toggle-active");
        $response->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_toggle(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $otherFace = Face::factory()->create();
        $regularUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);
        $userToken = $regularUser->createToken('user-token')->plainTextToken;

        $response = $this->withToken($userToken)
            ->patchJson("/api/v1/admin/faces/{$face->uuid}/toggle-active");
        $response->assertForbidden();
    }

    // ─── IS_ACTIVE IN RESPONSES ──────────────────────────────────

    public function test_is_active_field_in_admin_face_list_response(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'is_active' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['is_active']],
            ]);
    }
}
