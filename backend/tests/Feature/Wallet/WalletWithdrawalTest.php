<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;
    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id'   => $face->id,
        ]);
        // Use increment — balance not in $fillable
        $this->faceUser->increment('balance', 50000);

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id'   => $producer->id,
        ]);
    }

    private function mockFedapaySuccess(): void
    {
        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('initiateWithdrawal')->andReturn([
            'fedapay_payout_id' => 999,
            'status'            => 'approved',
        ]);
        $this->app->instance(FedapayService::class, $mock);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'amount'        => 20000,
            'payment_mode'  => 'mtn',
            'phone_number'  => '64000001',
            'phone_country' => 'bj',
        ], $overrides);
    }

    public function test_face_can_initiate_withdrawal_with_valid_data(): void
    {
        $this->mockFedapaySuccess();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_withdrawal_debits_user_balance_atomically(): void
    {
        $this->mockFedapaySuccess();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->faceUser->refresh();
        $this->assertSame(30000, $this->faceUser->balance); // 50000 - 20000
    }

    public function test_withdrawal_creates_debit_wallet_transaction_with_completed_status(): void
    {
        $this->mockFedapaySuccess();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id'    => $this->faceUser->id,
            'type'       => 'debit',
            'amount'     => 20000,
            'status'     => 'completed',
            'booking_id' => null,
        ]);
    }

    public function test_withdrawal_creates_financial_event(): void
    {
        $this->mockFedapaySuccess();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->assertDatabaseHas('financial_events', [
            'type'       => 'withdrawal',
            'booking_id' => null,
            'amount'     => 20000,
        ]);
    }

    public function test_withdrawal_rolls_back_when_fedapay_fails(): void
    {
        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('initiateWithdrawal')
            ->andThrow(new \Exception('Fedapay API error'));
        $this->app->instance(FedapayService::class, $mock);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Retrait échoué. Veuillez réessayer.');

        // Balance unchanged
        $this->faceUser->refresh();
        $this->assertSame(50000, $this->faceUser->balance);

        // No wallet transaction persisted
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('financial_events', 0);
    }

    public function test_withdrawal_fails_when_amount_exceeds_balance(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_withdrawal_fails_with_zero_amount(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_withdrawal_fails_with_invalid_payment_mode(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['payment_mode' => 'invalid_mode']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_mode']);
    }

    public function test_producer_cannot_access_withdrawal_endpoint(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_withdraw(): void
    {
        $this->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertUnauthorized();
    }
}
