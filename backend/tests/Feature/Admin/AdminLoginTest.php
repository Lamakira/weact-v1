<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $password = 'SecurePass123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'email' => 'admin@test.com',
            'password' => $this->password,
        ]);
    }

    public function test_successful_admin_login_returns_200_with_token(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'admin' => ['id', 'name', 'email'],
                    'token',
                ],
                'message',
                'meta',
            ])
            ->assertJsonPath('data.admin.email', 'admin@test.com')
            ->assertJsonPath('message', 'Connexion admin réussie');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_admin_login_with_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_admin_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'nonexistent@test.com',
            'password' => $this->password,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_admin_login_without_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'password' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_admin_login_without_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_admin_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'not-an-email',
            'password' => $this->password,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
