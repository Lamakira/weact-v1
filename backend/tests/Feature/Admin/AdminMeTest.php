<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'name' => 'Jean Admin',
            'email' => 'jean@weact.bj',
        ]);
    }

    public function test_me_returns_authenticated_admin_data(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'message',
            ])
            ->assertJsonPath('data.id', $this->admin->id)
            ->assertJsonPath('data.name', 'Jean Admin')
            ->assertJsonPath('data.email', 'jean@weact.bj')
            ->assertJsonPath('message', 'Profil admin récupéré avec succès');
    }

    public function test_me_without_auth_returns_401(): void
    {
        $response = $this->getJson('/api/v1/admin/me');

        $response->assertUnauthorized();
    }

    public function test_me_response_excludes_sensitive_fields(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/me');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertArrayNotHasKey('updated_at', $data);
        // created_at is intentionally included in AdminResource (needed for admin list)
    }

    public function test_non_admin_user_cannot_access_admin_me_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('user-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/me');

        $response->assertForbidden();
    }

    public function test_me_response_field_types(): void
    {
        $token = $this->admin->createToken('admin-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/me');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['email']);
    }
}
