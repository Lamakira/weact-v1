<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Face;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route protected by auth:sanctum + admin middleware
        Route::middleware(['auth:sanctum', 'admin'])->get('/api/v1/admin/test-protected', function () {
            return response()->json(['message' => 'Admin access granted']);
        });
    }

    public function test_admin_token_passes_middleware(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $response = $this->getJson('/api/v1/admin/test-protected', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Admin access granted');
    }

    public function test_no_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/test-protected');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_token_returns_403(): void
    {
        $face = Face::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'username' => 'johndoe',
        ]);

        $user = User::create([
            'email' => 'face@test.com',
            'password' => 'password',
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $token = $user->createToken('user-token')->plainTextToken;

        $response = $this->getJson('/api/v1/admin/test-protected', [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/test-protected', [
            'Authorization' => 'Bearer invalid-token-here',
        ]);

        $response->assertStatus(401);
    }
}
