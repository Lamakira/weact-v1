<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user (Face userable not required for logout tests)
        $this->user = User::factory()->create();

        // Create a token for the user
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_successful_logout_returns_200_and_revokes_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'data' => null,
                'message' => 'Déconnexion réussie',
                'meta' => [],
            ]);

        // Verify the token was revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_revoked_token_cannot_access_protected_routes(): void
    {
        // First, logout
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Try to access protected route with the same token
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_call_logout_endpoint(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_logout_only_revokes_current_token(): void
    {
        // Create a second token for the same user
        $secondToken = $this->user->createToken('second-token')->plainTextToken;

        // Logout using the first token
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // The second token should still work
        $response = $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
    }

    public function test_logout_with_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
