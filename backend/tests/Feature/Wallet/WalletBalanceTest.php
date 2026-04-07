<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletBalanceTest extends TestCase
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
            'balance' => 0,
        ]);

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    public function test_face_can_view_wallet_with_zero_balance(): void
    {
        $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.pending_escrow', 0)
            ->assertJsonPath('data.withdrawal_mode', 'manual')
            ->assertJsonStructure([
                'data' => [
                    'balance',
                    'pending_escrow',
                    'withdrawal_mode',
                    'withdrawal_requests',
                    'transactions',
                    'transactions_meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.withdrawal_requests', [])
            ->assertJsonPath('data.transactions', []);
    }

    public function test_face_wallet_shows_real_balance(): void
    {
        $this->faceUser->increment('balance', 42500);

        $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance', 42500);
    }

    public function test_face_wallet_shows_pending_escrow_from_locked_bookings(): void
    {
        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Paid,
        ]);
        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 30000,
            'status' => 'locked',
        ]);

        $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.pending_escrow', 30000);
    }

    public function test_pending_escrow_does_not_include_released_escrow(): void
    {
        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Completed,
        ]);
        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 30000,
            'status' => 'released',
            'released_at' => now(),
        ]);

        $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.pending_escrow', 0);
    }

    public function test_face_wallet_transactions_appear_in_list(): void
    {
        WalletTransaction::factory()->count(3)->create([
            'user_id' => $this->faceUser->id,
            'type' => 'credit',
            'amount' => 42500,
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $this->assertCount(3, $response->json('data.transactions'));
        $this->assertSame(3, $response->json('data.transactions_meta.total'));
    }

    public function test_wallet_transactions_are_ordered_latest_first(): void
    {
        $old = WalletTransaction::factory()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 10000,
            'created_at' => now()->subDays(5),
        ]);
        $recent = WalletTransaction::factory()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 25000,
            'created_at' => now(),
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $transactions = $response->json('data.transactions');
        $this->assertSame($recent->id, $transactions[0]['id']);
        $this->assertSame($old->id, $transactions[1]['id']);
    }

    public function test_wallet_only_returns_current_user_transactions(): void
    {
        // Another Face user with transactions
        $otherFace = Face::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);
        WalletTransaction::factory()->count(5)->create(['user_id' => $otherUser->id]);

        // Our user has 2 transactions
        WalletTransaction::factory()->count(2)->create(['user_id' => $this->faceUser->id]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $this->assertCount(2, $response->json('data.transactions'));
    }

    public function test_wallet_includes_current_user_withdrawal_requests_latest_first(): void
    {
        $otherFace = Face::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        WithdrawalRequest::query()->create([
            'user_id' => $otherUser->id,
            'amount' => 99000,
            'payment_mode' => 'mtn',
            'phone_number' => '61000000',
            'phone_country' => 'bj',
            'status' => 'pending',
        ]);

        $older = WithdrawalRequest::query()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 12500,
            'payment_mode' => 'moov',
            'phone_number' => '97000001',
            'phone_country' => 'bj',
            'status' => 'approved',
            'notes' => 'Traité',
            'processed_at' => now()->subDay(),
        ]);

        $latest = WithdrawalRequest::query()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 25000,
            'payment_mode' => 'mtn',
            'phone_number' => '64000001',
            'phone_country' => 'bj',
            'status' => 'pending',
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $requests = $response->json('data.withdrawal_requests');

        $this->assertCount(2, $requests);
        $this->assertSame($latest->id, $requests[0]['id']);
        $this->assertSame($older->id, $requests[1]['id']);
    }

    public function test_wallet_withdrawal_request_resource_fields_are_correct(): void
    {
        $withdrawalRequest = WithdrawalRequest::query()->create([
            'user_id' => $this->faceUser->id,
            'amount' => 42500,
            'payment_mode' => 'mtn',
            'phone_number' => '64000001',
            'phone_country' => 'bj',
            'status' => 'rejected',
            'notes' => 'Numéro invalide',
            'processed_at' => now(),
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $request = $response->json('data.withdrawal_requests.0');

        $this->assertSame($withdrawalRequest->id, $request['id']);
        $this->assertSame(42500, $request['amount']);
        $this->assertSame('mtn', $request['payment_mode']);
        $this->assertSame('64000001', $request['phone_number']);
        $this->assertSame('bj', $request['phone_country']);
        $this->assertSame('rejected', $request['status']);
        $this->assertSame('Numéro invalide', $request['notes']);
        $this->assertNotNull($request['processed_at']);
        $this->assertArrayHasKey('created_at', $request);
    }

    public function test_producer_can_view_wallet_with_zero_pending_escrow(): void
    {
        $this->withApiToken($this->producerUser)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.pending_escrow', 0)
            ->assertJsonPath('data.withdrawal_requests', [])
            ->assertJsonPath('data.transactions', []);
    }

    public function test_unauthenticated_user_cannot_access_wallet(): void
    {
        $this->getJson('/api/v1/wallet')->assertUnauthorized();
    }

    public function test_wallet_transactions_paginate_correctly(): void
    {
        // Create 16 transactions — first page should return 15, second page should return 1
        WalletTransaction::factory()->count(16)->create(['user_id' => $this->faceUser->id]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $this->assertCount(15, $response->json('data.transactions'));
        $this->assertSame(16, $response->json('data.transactions_meta.total'));
        $this->assertSame(2, $response->json('data.transactions_meta.last_page'));
        $this->assertSame(1, $response->json('data.transactions_meta.current_page'));

        // Page 2 should return the remaining 1 transaction
        $page2 = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet?page=2')
            ->assertOk();

        $this->assertCount(1, $page2->json('data.transactions'));
        $this->assertSame(2, $page2->json('data.transactions_meta.current_page'));
    }

    public function test_wallet_transaction_resource_fields_are_correct(): void
    {
        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        WalletTransaction::factory()->create([
            'user_id' => $this->faceUser->id,
            'booking_id' => $booking->id,
            'type' => 'credit',
            'amount' => 42500,
            'reference' => 'wlt_test-ref',
            'description' => 'Test payment',
        ]);

        $response = $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/wallet')
            ->assertOk();

        $tx = $response->json('data.transactions.0');
        $this->assertSame('credit', $tx['type']);
        $this->assertSame(42500, $tx['amount']);
        $this->assertSame('wlt_test-ref', $tx['reference']);
        $this->assertSame('Test payment', $tx['description']);
        $this->assertSame($booking->id, $tx['booking_id']);
        $this->assertArrayHasKey('created_at', $tx);
    }
}
