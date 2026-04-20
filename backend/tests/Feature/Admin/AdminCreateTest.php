<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreateTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    public function test_creates_a_new_admin_with_valid_data(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Nouveau Admin',
                'email' => 'nouveau@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'role', 'created_at'],
                'message',
            ])
            ->assertJsonPath('data.name', 'Nouveau Admin')
            ->assertJsonPath('data.email', 'nouveau@weact.bj')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('message', 'Administrateur créé avec succès');

        $this->assertDatabaseHas('admins', [
            'name' => 'Nouveau Admin',
            'email' => 'nouveau@weact.bj',
        ]);
    }

    public function test_hashes_the_password_when_creating_admin(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Admin Test',
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $newAdmin = Admin::where('email', 'test@weact.bj')->first();

        $this->assertNotNull($newAdmin);
        $this->assertNotEquals('Password1!', $newAdmin->password);
    }

    public function test_rejects_duplicate_email(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Duplicate Admin',
                'email' => $this->admin->email,
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.email.0', 'Cet email est déjà utilisé.');
    }

    public function test_rejects_missing_name(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.name.0', 'Le nom est obligatoire.');
    }

    public function test_rejects_invalid_email_format(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test Admin',
                'email' => 'not-an-email',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.email.0', "L'email doit être une adresse email valide.");
    }

    public function test_rejects_password_shorter_than_8_characters(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test Admin',
                'email' => 'test@weact.bj',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Le mot de passe doit contenir au moins 8 caractères.');
    }

    public function test_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test Admin',
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'DifferentPassword!',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'La confirmation du mot de passe ne correspond pas.');
    }

    public function test_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->postJson('/api/v1/admin/admins', [
            'name' => 'Test Admin',
            'email' => 'test@weact.bj',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertStatus(401);
    }

    public function test_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('user-token')->plainTextToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test Admin',
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

        $response->assertStatus(403);
    }

    public function test_creates_admin_with_explicit_role(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Editor Admin',
                'email' => 'editor@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'editor',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.role', 'editor');

        $this->assertDatabaseHas('admins', [
            'email' => 'editor@weact.bj',
            'role' => AdminRole::Editor->value,
        ]);
    }

    public function test_rejects_invalid_role_on_create(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Test Admin',
                'email' => 'test@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_newly_created_admin_can_login(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'Login Test Admin',
                'email' => 'logintest@weact.bj',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertStatus(201);

        $loginResponse = $this->postJson('/api/v1/admin/login', [
            'email' => 'logintest@weact.bj',
            'password' => 'Password1!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('data.admin.email', 'logintest@weact.bj');
    }
}
