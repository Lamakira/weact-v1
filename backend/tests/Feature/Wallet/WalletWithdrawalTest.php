<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Exceptions\WithdrawalLockException;
use App\Mail\WithdrawalRequestSubmittedMail;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private function withApiToken(User $user): static
    {
        return $this->withToken($user->createToken('test-token')->plainTextToken);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        // Use increment — balance not in $fillable
        $this->faceUser->increment('balance', 50000);

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function mockFedapaySuccess(): void
    {
        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('initiateWithdrawal')->andReturn([
            'fedapay_payout_id' => 999,
            'status' => 'approved',
        ]);
        $this->app->instance(FedapayService::class, $mock);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 20000,
            'payment_mode' => 'mtn',
            'phone_number' => '0197000000', // valid MTN BJ EZAB prefix (10 digits)
            'phone_country' => 'bj',
        ], $overrides);
    }

    public function test_face_can_initiate_withdrawal_with_valid_data(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $this->mockFedapaySuccess();

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('withdrawal_mode', 'fedapay');
    }

    public function test_fedapay_withdrawal_debits_user_balance_atomically(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $this->mockFedapaySuccess();

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->faceUser->refresh();
        $this->assertSame(30000, $this->faceUser->balance); // 50000 - 20000
    }

    public function test_fedapay_withdrawal_creates_pending_wallet_transaction(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $this->mockFedapaySuccess();

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->faceUser->id,
            'type' => 'debit',
            'amount' => 20000,
            'status' => 'pending',
            'booking_id' => null,
        ]);
    }

    public function test_fedapay_withdrawal_creates_pending_financial_event(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $this->mockFedapaySuccess();

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk();

        $this->assertDatabaseHas('financial_events', [
            'type' => 'withdrawal',
            'booking_id' => null,
            'amount' => 20000,
            'status' => 'pending',
            'fedapay_ref' => '999',
        ]);
    }

    public function test_withdrawal_rolls_back_when_fedapay_fails(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $mock = Mockery::mock(FedapayService::class);
        $mock->shouldReceive('initiateWithdrawal')
            ->andThrow(new \Exception('Fedapay API error'));
        $this->app->instance(FedapayService::class, $mock);

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'WITHDRAWAL_FAILED')
            ->assertJsonPath('error.message', 'Retrait échoué. Veuillez réessayer.');

        // Balance unchanged
        $this->faceUser->refresh();
        $this->assertSame(50000, $this->faceUser->balance);

        // No wallet transaction persisted
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('financial_events', 0);
    }

    public function test_manual_withdrawal_creates_pending_request_without_debiting_balance(): void
    {
        config([
            'app.withdrawal_mode' => 'manual',
            'app.admin_email' => 'admin@example.com',
        ]);
        Mail::fake();

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 20000]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('withdrawal_mode', 'manual');

        $this->faceUser->refresh();
        $this->assertSame(50000, $this->faceUser->balance);

        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $this->faceUser->id,
            'amount' => 20000,
            'status' => 'pending',
            'payment_mode' => 'mtn',
            'phone_number' => '0197000000',
        ]);

        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('financial_events', 0);
        Mail::assertSent(WithdrawalRequestSubmittedMail::class);
    }

    public function test_withdrawal_fails_when_amount_exceeds_balance(): void
    {
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_withdrawal_fails_with_zero_amount(): void
    {
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_withdrawal_fails_with_invalid_payment_mode(): void
    {
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['payment_mode' => 'invalid_mode']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_mode']);
    }

    public function test_producer_can_submit_withdrawal_request(): void
    {
        $this->producerUser->increment('balance', 50000);

        config([
            'app.withdrawal_mode' => 'manual',
            'app.admin_email' => 'admin@example.com',
        ]);
        Mail::fake();

        $this->withApiToken($this->producerUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 10000]))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('withdrawal_mode', 'manual');

        $this->producerUser->refresh();
        $this->assertSame(50000, $this->producerUser->balance); // manual mode: not debited

        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $this->producerUser->id,
            'amount' => 10000,
            'status' => 'pending',
        ]);
    }

    public function test_producer_cannot_submit_withdrawal_when_one_is_already_pending(): void
    {
        $this->producerUser->increment('balance', 50000);

        config([
            'app.withdrawal_mode' => 'manual',
            'app.admin_email' => 'admin@example.com',
        ]);
        Mail::fake();

        // First request succeeds
        $this->withApiToken($this->producerUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 5000]))
            ->assertOk();

        // Second request blocked
        $this->withApiToken($this->producerUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_unauthenticated_user_cannot_withdraw(): void
    {
        $this->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_withdrawal_fails_below_minimum_amount(): void
    {
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 499]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_manual_mode_blocks_second_pending_request(): void
    {
        config([
            'app.withdrawal_mode' => 'manual',
            'app.admin_email' => 'admin@example.com',
        ]);
        Mail::fake();

        // First request succeeds
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 5000]))
            ->assertOk();

        // Second request blocked — pending already exists
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload(['amount' => 5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_benin_phone_fails_with_wrong_operator_prefix(): void
    {
        // 0197 is an MTN prefix — submitting it under Moov must fail
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload([
                'payment_mode' => 'moov',
                'phone_number' => '0197000000',
                'phone_country' => 'bj',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_benin_phone_fails_when_not_10_digits(): void
    {
        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload([
                'phone_number' => '019700000', // 9 digits
                'phone_country' => 'bj',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_concurrent_fedapay_withdrawal_returns_409_with_correct_message(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        $mock = Mockery::mock(WithdrawalService::class);
        $mock->shouldReceive('initiate')
            ->andThrow(new WithdrawalLockException('Un retrait est déjà en cours pour ce compte. Veuillez patienter.'));
        $this->app->instance(WithdrawalService::class, $mock);

        $this->withApiToken($this->faceUser)
            ->postJson('/api/v1/wallet/withdraw', $this->validPayload())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'WITHDRAWAL_LOCK')
            ->assertJsonPath('error.message', 'Un retrait est déjà en cours pour ce compte. Veuillez patienter.');
    }
}
