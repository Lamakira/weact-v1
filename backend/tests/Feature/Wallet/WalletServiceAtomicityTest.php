<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * credit / creditDirect / debit sont atomiques par eux-mêmes : mise à jour du
 * solde + ligne de ledger tout-ou-rien, même hors DB::transaction() appelante.
 */
class WalletServiceAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallet = new WalletService;
        $this->user = User::factory()->create(['balance' => 5000]);
    }

    private function failLedgerInsert(): void
    {
        WalletTransaction::creating(function (): void {
            throw new RuntimeException('ledger insert failed');
        });
    }

    private function assertNothingPersisted(): void
    {
        $this->assertSame(5000, (int) $this->user->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_credit_rolls_back_balance_when_ledger_insert_fails_outside_a_transaction(): void
    {
        $booking = Booking::factory()->create();
        $this->failLedgerInsert();

        try {
            $this->wallet->credit($this->user->id, 1000, $booking, 'test');
            $this->fail('Exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('ledger insert failed', $e->getMessage());
        }

        $this->assertNothingPersisted();
    }

    public function test_credit_direct_rolls_back_balance_when_ledger_insert_fails_outside_a_transaction(): void
    {
        $this->failLedgerInsert();

        try {
            $this->wallet->creditDirect($this->user->id, 1000, 'test');
            $this->fail('Exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('ledger insert failed', $e->getMessage());
        }

        $this->assertNothingPersisted();
    }

    public function test_debit_rolls_back_balance_when_ledger_insert_fails_outside_a_transaction(): void
    {
        $this->failLedgerInsert();

        try {
            $this->wallet->debit($this->user->id, 1000, 'test');
            $this->fail('Exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('ledger insert failed', $e->getMessage());
        }

        $this->assertNothingPersisted();
    }

    public function test_credit_inside_outer_transaction_is_rolled_back_with_it(): void
    {
        $booking = Booking::factory()->create();

        try {
            DB::transaction(function () use ($booking): void {
                $this->wallet->credit($this->user->id, 1000, $booking, 'test');
                $this->wallet->creditDirect($this->user->id, 500, 'test');
                $this->wallet->debit($this->user->id, 200, 'test');
                throw new RuntimeException('outer failure');
            });
            $this->fail('Exception attendue');
        } catch (RuntimeException $e) {
            $this->assertSame('outer failure', $e->getMessage());
        }

        $this->assertNothingPersisted();
    }

    public function test_happy_path_still_returns_ledger_row_and_moves_balance(): void
    {
        $tx = $this->wallet->creditDirect($this->user->id, 1000, 'ok');
        $this->assertSame('credit', $tx->type);
        $this->assertSame(6000, (int) $this->user->fresh()->balance);

        $tx = $this->wallet->debit($this->user->id, 2500, 'ok');
        $this->assertSame('debit', $tx->type);
        $this->assertSame(3500, (int) $this->user->fresh()->balance);
    }
}
