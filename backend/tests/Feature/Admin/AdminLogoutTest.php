<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLogoutTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_successful_logout_returns_200_and_revokes_token(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Déconnexion réussie');

        // Verify token is revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_without_auth_returns_401(): void
    {
        $response = $this->postJson('/api/v1/admin/logout');

        $response->assertUnauthorized();
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Logout
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout')
            ->assertOk();

        // Verify token is deleted from database — proves revocation
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_admin_user_cannot_use_admin_logout_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('user-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertForbidden();
    }

    public function test_logout_with_already_revoked_token_returns_401(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        // Manually revoke the token
        $this->admin->tokens()->delete();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/admin/logout');

        $response->assertUnauthorized();
    }
}
