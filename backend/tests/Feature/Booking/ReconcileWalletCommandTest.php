<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileWalletCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_success_when_no_wallet_transactions(): void
    {
        $this->artisan('bookings:reconcile-wallet')
            ->assertExitCode(0);
    }

    public function test_command_exits_success_when_balance_matches_transactions(): void
    {
        $user = User::factory()->create(['balance' => 45000]);

        WalletTransaction::create([
            'user_id' => $user->id,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => 50000,
            'reference' => 'ref-1',
            'description' => 'Escrow release',
            'status' => 'completed',
        ]);

        WalletTransaction::create([
            'user_id' => $user->id,
            'booking_id' => null,
            'type' => 'debit',
            'amount' => 5000,
            'reference' => 'ref-2',
            'description' => 'Withdrawal',
            'status' => 'completed',
        ]);

        // balance = 50000 - 5000 = 45000 → matches users.balance
        $this->artisan('bookings:reconcile-wallet')
            ->assertExitCode(0);
    }

    public function test_command_exits_failure_when_balance_diverges_from_transactions(): void
    {
        $user = User::factory()->create(['balance' => 0]);

        WalletTransaction::create([
            'user_id' => $user->id,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => 80000,
            'reference' => 'ref-diverge',
            'description' => 'Escrow release',
            'status' => 'completed',
        ]);

        // expected = 80000, actual users.balance = 0 → discrepancy
        $this->artisan('bookings:reconcile-wallet')
            ->assertExitCode(1);
    }

    public function test_command_reports_only_divergent_users_among_multiple(): void
    {
        // User A: balanced
        $userA = User::factory()->create(['balance' => 30000]);
        WalletTransaction::create([
            'user_id' => $userA->id,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => 30000,
            'reference' => 'ref-a',
            'description' => 'Escrow release',
            'status' => 'completed',
        ]);

        // User B: divergent
        $userB = User::factory()->create(['balance' => 0]);
        WalletTransaction::create([
            'user_id' => $userB->id,
            'booking_id' => null,
            'type' => 'credit',
            'amount' => 20000,
            'reference' => 'ref-b',
            'description' => 'Escrow release',
            'status' => 'completed',
        ]);

        // Only userB diverges → command must exit 1
        $this->artisan('bookings:reconcile-wallet')
            ->assertExitCode(1);

        // Verify userA's balance is untouched (no false positive)
        $this->assertEquals(30000, $userA->fresh()->balance);
    }

    public function test_command_exits_success_when_user_has_multiple_credits_and_debits(): void
    {
        $user = User::factory()->create(['balance' => 60000]);

        foreach ([30000, 40000, 20000] as $i => $amount) {
            WalletTransaction::create([
                'user_id' => $user->id,
                'booking_id' => null,
                'type' => 'credit',
                'amount' => $amount,
                'reference' => "credit-{$i}",
                'description' => 'Credit',
                'status' => 'completed',
            ]);
        }

        foreach ([20000, 10000] as $i => $amount) {
            WalletTransaction::create([
                'user_id' => $user->id,
                'booking_id' => null,
                'type' => 'debit',
                'amount' => $amount,
                'reference' => "debit-{$i}",
                'description' => 'Debit',
                'status' => 'completed',
            ]);
        }

        // credits = 90000, debits = 30000, expected = 60000 → matches balance
        $this->artisan('bookings:reconcile-wallet')
            ->assertExitCode(0);
    }
}
