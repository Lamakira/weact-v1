<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Face;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_overview_includes_pending_withdrawal_requests(): void
    {
        $admin = Admin::factory()->create();

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

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.withdrawal_requests.pending_count', 2)
            ->assertJsonPath('data.withdrawal_requests.pending_amount', 20000);
    }
}
