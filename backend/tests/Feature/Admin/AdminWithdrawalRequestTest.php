<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Face;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminWithdrawalRequestTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();

        $face = Face::factory()->create([
            'prenom' => 'Amina',
        ]);

        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $this->faceUser->increment('balance', 50000);
    }

    public function test_admin_can_list_withdrawal_requests(): void
    {
        WithdrawalRequest::factory()->create([
            'user_id' => $this->faceUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/finance/withdrawal-requests?status=pending');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_email', $this->faceUser->email)
            ->assertJsonPath('data.0.user_prenom', 'Amina')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_admin_can_approve_pending_withdrawal_request(): void
    {
        Mail::fake();

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 20000,
            'payment_mode' => 'mtn',
            'phone_number' => '64000001',
            'phone_country' => 'bj',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->id}/approve", [
                'notes' => 'Traite via depot manuel.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->faceUser->refresh();
        $withdrawalRequest->refresh();

        $this->assertSame(30000, $this->faceUser->balance);
        $this->assertSame('approved', $withdrawalRequest->status);
        $this->assertNotNull($withdrawalRequest->wallet_transaction_id);
        $this->assertSame($this->admin->id, $withdrawalRequest->processed_by_admin_id);

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $withdrawalRequest->wallet_transaction_id,
            'user_id' => $this->faceUser->id,
            'type' => 'debit',
            'amount' => 20000,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('financial_events', [
            'type' => 'withdrawal',
            'amount' => 20000,
            'status' => 'completed',
            'idempotency_key' => "manual_withdrawal_{$withdrawalRequest->id}_approved",
        ]);
    }

    public function test_admin_gets_explicit_error_if_balance_is_insufficient_at_approval(): void
    {
        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 20000,
            'status' => 'pending',
        ]);

        User::query()
            ->whereKey($this->faceUser->id)
            ->update(['balance' => 5000]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solde insuffisant au moment de l’approbation. La Face a probablement depense une partie de ses fonds.');

        $withdrawalRequest->refresh();
        $this->assertSame('pending', $withdrawalRequest->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_admin_can_reject_pending_withdrawal_request(): void
    {
        Mail::fake();

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $this->faceUser->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->id}/reject", [
                'notes' => 'Numero invalide',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $withdrawalRequest->refresh();
        $this->assertSame('rejected', $withdrawalRequest->status);
        $this->assertSame('Numero invalide', $withdrawalRequest->notes);
        $this->assertSame($this->admin->id, $withdrawalRequest->processed_by_admin_id);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
