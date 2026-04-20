<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\WithdrawalApprovedMail;
use App\Mail\WithdrawalRejectedMail;
use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
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

    private function withAdminApiToken(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-token')->plainTextToken);
    }

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

        $response = $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/finance/withdrawal-requests?status=pending');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_email', $this->faceUser->email)
            ->assertJsonPath('data.0.user_name', (string) $this->faceUser->userable?->display_name)
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

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->uuid}/approve", [
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

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->uuid}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INSUFFICIENT_BALANCE')
            ->assertJsonPath('error.message', 'Solde insuffisant au moment de l’approbation. L’utilisateur a probablement dépensé une partie de ses fonds.');

        $withdrawalRequest->refresh();
        $this->assertSame('pending', $withdrawalRequest->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_admin_sees_producer_withdrawal_with_correct_name(): void
    {
        $producer = Producer::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'type' => 'particulier',
        ]);

        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $producerUser->increment('balance', 30000);

        WithdrawalRequest::factory()->create([
            'user_id' => $producerUser->id,
            'status' => 'pending',
        ]);

        $response = $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/finance/withdrawal-requests?status=pending');

        $response->assertOk();

        $data = $response->json('data');
        $producerEntry = collect($data)->firstWhere('user_email', $producerUser->email);

        $this->assertNotNull($producerEntry);
        $this->assertSame('Kofi Mensah', $producerEntry['user_name']);
    }

    public function test_admin_can_reject_pending_withdrawal_request(): void
    {
        Mail::fake();

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $this->faceUser->id,
            'status' => 'pending',
        ]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->uuid}/reject", [
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

    public function test_producer_manual_request_email_uses_producer_name(): void
    {
        Mail::fake();

        $producer = Producer::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'type' => 'particulier',
        ]);

        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $producerUser->id,
            'status' => 'pending',
            'amount' => 15000,
        ])->load('user.userable');

        $mail = new \App\Mail\WithdrawalRequestSubmittedMail($withdrawalRequest);

        $this->assertStringContainsString('Kofi Mensah', $mail->render());
        $this->assertSame('Nouvelle demande de retrait - Kofi Mensah 15 000 XOF', $mail->envelope()->subject);
    }

    public function test_producer_approval_email_uses_producer_name(): void
    {
        Mail::fake();

        $producer = Producer::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'type' => 'particulier',
        ]);

        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'balance' => 30000,
        ]);

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $producerUser->id,
            'amount' => 10000,
            'status' => 'pending',
        ]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->uuid}/approve", [
                'notes' => 'Traite via depot manuel.',
            ])
            ->assertOk();

        Mail::assertSent(WithdrawalApprovedMail::class, function (WithdrawalApprovedMail $mail): bool {
            return str_contains($mail->render(), 'Bonjour Kofi Mensah');
        });
    }

    public function test_producer_rejection_email_uses_producer_name(): void
    {
        Mail::fake();

        $producer = Producer::factory()->create([
            'first_name' => 'Kofi',
            'last_name' => 'Mensah',
            'type' => 'particulier',
        ]);

        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $withdrawalRequest = WithdrawalRequest::factory()->create([
            'user_id' => $producerUser->id,
            'amount' => 10000,
            'status' => 'pending',
        ]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/finance/withdrawal-requests/{$withdrawalRequest->uuid}/reject", [
                'notes' => 'Numero invalide',
            ])
            ->assertOk();

        Mail::assertSent(WithdrawalRejectedMail::class, function (WithdrawalRejectedMail $mail): bool {
            return str_contains($mail->render(), 'Bonjour Kofi Mensah');
        });
    }
}
