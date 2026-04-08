<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminFinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function withAdminApiToken(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-token')->plainTextToken);
    }

    public function test_finance_overview_includes_pending_withdrawal_requests(): void
    {
        $admin = Admin::factory()->create();
        $this->mockFedapayBalances();

        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        WithdrawalRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 12000,
        ]);
        WithdrawalRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 8000,
        ]);
        WithdrawalRequest::factory()->rejected()->create([
            'user_id' => $user->id,
            'amount' => 25000,
        ]);

        $this->withAdminApiToken($admin)
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.withdrawal_requests.pending_count', 2)
            ->assertJsonPath('data.withdrawal_requests.pending_amount', 20000)
            ->assertJsonPath('data.fedapay_balance.available', true)
            ->assertJsonPath('data.fedapay_balance.total_amount', 90000);
    }

    public function test_finance_overview_handles_unavailable_fedapay_balance(): void
    {
        $admin = Admin::factory()->create();

        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('getBalanceSummary')->andReturn([
            'available' => false,
            'total_amount' => null,
            'refreshed_at' => now()->toIso8601String(),
            'error' => 'Impossible de récupérer le solde FedaPay pour le moment.',
        ]);
        $this->app->instance(FedapayService::class, $mock);

        $this->withAdminApiToken($admin)
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.fedapay_balance.available', false)
            ->assertJsonPath('data.fedapay_balance.error', 'Impossible de récupérer le solde FedaPay pour le moment.');
    }

    public function test_finance_overview_includes_producer_balances_in_retirable_total(): void
    {
        $admin = Admin::factory()->create();
        $this->mockFedapayBalances();

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'balance' => 12000,
        ]);

        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'balance' => 8000,
        ]);

        $this->withAdminApiToken($admin)
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.retirable.amount', 20000)
            ->assertJsonPath('data.retirable.face_balance', 12000)
            ->assertJsonPath('data.retirable.producer_balance', 8000);
    }

    private function mockFedapayBalances(): void
    {
        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('getBalanceSummary')->andReturn([
            'available' => true,
            'total_amount' => 90000,
            'refreshed_at' => now()->toIso8601String(),
            'error' => null,
        ]);
        $this->app->instance(FedapayService::class, $mock);
    }
}
