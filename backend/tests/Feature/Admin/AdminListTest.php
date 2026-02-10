<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create();
    }

    public function test_returns_paginated_list_of_admins(): void
    {
        Admin::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_returns_admins_ordered_by_created_at_desc(): void
    {
        $olderAdmin = Admin::factory()->create(['created_at' => now()->subDay()]);
        $newerAdmin = Admin::factory()->create(['created_at' => now()->addDay()]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $data = $response->json('data');

        $this->assertEquals($newerAdmin->id, $data[0]['id']);
    }

    public function test_paginates_results_correctly(): void
    {
        Admin::factory()->count(16)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/admins?page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(15, 'data');

        $page2 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/admins?page=2');

        $page2->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_list_with_single_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/admin/admins');

        $response->assertStatus(401);
    }

    public function test_returns_403_for_non_admin_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/admins');

        $response->assertStatus(403);
    }
}
