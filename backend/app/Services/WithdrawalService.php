<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancialEventType;
use App\Exceptions\WithdrawalLockException;
use App\Mail\WithdrawalRequestSubmittedMail;
use App\Models\FinancialEvent;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WithdrawalService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly FedapayService $fedapayService,
    ) {}

    /**
     * @param  array{amount: int, payment_mode: string, phone_number: string, phone_country: string}  $validated
     * @return array{mode: 'manual'|'fedapay', message: string}
     */
    public function initiate(User $user, array $validated): array
    {
        $mode = config('app.withdrawal_mode', 'manual');

        if ($mode === 'manual') {
            $this->createManualRequest($user, $validated);

            return [
                'mode' => 'manual',
                'message' => 'Votre demande de retrait a été soumise. Elle sera traitée sous 48h.',
            ];
        }

        $this->initiateFedapayWithdrawal($user, $validated);

        return [
            'mode' => 'fedapay',
            'message' => 'Retrait initié avec succès.',
        ];
    }

    /**
     * @param  array{amount: int, payment_mode: string, phone_number: string, phone_country: string}  $validated
     */
    public function createManualRequest(User $user, array $validated): WithdrawalRequest
    {
        $withdrawalRequest = WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'payment_mode' => $validated['payment_mode'],
            'phone_number' => $validated['phone_number'],
            'phone_country' => $validated['phone_country'],
            'status' => 'pending',
        ]);

        $adminEmail = (string) config('app.admin_email', '');

        if ($adminEmail !== '') {
            Mail::to($adminEmail)->send(
                new WithdrawalRequestSubmittedMail($withdrawalRequest->loadMissing('user.userable'))
            );
        } else {
            Log::warning('Withdrawal request created but admin_email is not configured — no notification sent to admin', [
                'withdrawal_request_id' => $withdrawalRequest->id,
                'user_id'               => $user->id,
            ]);
        }

        return $withdrawalRequest;
    }

    /**
     * @param  array{amount: int, payment_mode: string, phone_number: string, phone_country: string}  $validated
     */
    private function initiateFedapayWithdrawal(User $user, array $validated): void
    {
        $lock = Cache::lock("withdrawal_fedapay_{$user->id}", 30);

        if (! $lock->get()) {
            throw new WithdrawalLockException('Un retrait est déjà en cours pour ce compte. Veuillez patienter.');
        }

        try {
            $idempotencyKey = 'withdrawal_' . Str::uuid()->toString();

            DB::transaction(function () use ($user, $validated, $idempotencyKey): void {
                $tx = $this->walletService->debit(
                    userId: $user->id,
                    amount: $validated['amount'],
                    description: $this->buildDescription($validated['payment_mode'], $validated['phone_number'], false),
                );

                $fedapayResult = $this->fedapayService->initiateWithdrawal(
                    amount: $validated['amount'],
                    mode: $validated['payment_mode'],
                    idempotencyKey: $idempotencyKey,
                    phoneData: [
                        'number' => $validated['phone_number'],
                        'country' => $validated['phone_country'],
                    ],
                    user: $user,
                );

                FinancialEvent::create([
                    'type' => FinancialEventType::Withdrawal,
                    'booking_id' => null,
                    'amount' => $validated['amount'],
                    'fedapay_ref' => (string) $fedapayResult['fedapay_payout_id'],
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'pending',
                    'metadata' => [
                        'payment_mode' => $validated['payment_mode'],
                        'phone_number' => $validated['phone_number'],
                        'phone_country' => $validated['phone_country'],
                        'fedapay_status' => $fedapayResult['status'],
                        'wallet_transaction_id' => $tx->id,
                        'user_id' => $user->id,
                    ],
                ]);
            });
        } finally {
            $lock->release();
        }
    }

    public function buildDescription(string $paymentMode, string $phoneNumber, bool $manual): string
    {
        $prefix = $manual ? 'Retrait manuel vers' : 'Retrait vers';

        return "{$prefix} {$paymentMode} — {$phoneNumber}";
    }
}
