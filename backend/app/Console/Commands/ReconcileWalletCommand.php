<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileWalletCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:reconcile-wallet';

    /**
     * The console command description.
     */
    protected $description = 'Daily reconciliation — compare wallet_transactions totals with users.balance';

    /**
     * Execute the console command.
     *
     * Uses set-based queries (3 total) instead of per-user queries to stay efficient at scale.
     */
    public function handle(): int
    {
        // Single GROUP BY query: compute expected balance per user from wallet_transactions
        $expectedBalances = WalletTransaction::selectRaw(
            'user_id,
             SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) -
             SUM(CASE WHEN type = "debit"  THEN amount ELSE 0 END) AS expected_balance'
        )->groupBy('user_id')->pluck('expected_balance', 'user_id');

        $checked = $expectedBalances->count();

        if ($checked === 0) {
            $this->info('Reconciliation OK — 0 user(s) checked.');
            Log::info('Reconciliation OK — 0 user(s) checked.', ['timestamp' => now()->toIso8601String()]);

            return self::SUCCESS;
        }

        // Single query: fetch actual balances for all relevant users
        $actualBalances = User::whereIn('id', $expectedBalances->keys())->pluck('balance', 'id');

        $discrepancies = [];

        foreach ($expectedBalances as $userId => $expected) {
            $expected = (int) $expected;
            $actual   = (int) ($actualBalances->get($userId) ?? 0);

            if ($expected !== $actual) {
                $discrepancy = [
                    'user_id'          => $userId,
                    'expected_balance' => $expected,
                    'actual_balance'   => $actual,
                    'difference'       => $expected - $actual,
                ];

                $discrepancies[] = $discrepancy;

                Log::warning('Wallet reconciliation discrepancy detected', $discrepancy);
                $this->error("Discrepancy for user #{$userId}: expected={$expected}, actual={$actual}, diff=" . ($expected - $actual));
            }
        }

        if (empty($discrepancies)) {
            $this->info("Reconciliation OK — {$checked} user(s) checked.");
            Log::info("Reconciliation OK — {$checked} user(s) checked.", ['timestamp' => now()->toIso8601String()]);

            return self::SUCCESS;
        }

        $count = count($discrepancies);
        $this->error("{$count} discrepanc" . ($count === 1 ? 'y' : 'ies') . " found out of {$checked} user(s). Check application logs.");

        return self::FAILURE;
    }
}
