<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\WithdrawWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\EscrowTransaction;
use App\Models\FinancialEvent;
use App\Models\WalletTransaction;
use App\Enums\FinancialEventType;
use App\Services\FedapayService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function index(Request $request): WalletResource
    {
        $user = $request->user();

        /** @var int $pendingEscrow */
        $pendingEscrow = (int) EscrowTransaction::whereHas('booking', function ($query) use ($user): void {
            $query->where('face_id', $user->id);
        })
            ->where('status', 'locked')
            ->sum('amount');

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return new WalletResource([
            'balance'        => (int) $user->balance,
            'pending_escrow' => $pendingEscrow,
            'transactions'   => $transactions,
        ]);
    }

    public function withdraw(
        WithdrawWalletRequest $request,
        WalletService $walletService,
        FedapayService $fedapayService,
    ): JsonResponse {
        $user = $request->user();
        $validated = $request->validated();

        $idempotencyKey = 'withdrawal_' . Str::uuid()->toString();

        try {
            DB::transaction(function () use ($user, $validated, $walletService, $fedapayService, $idempotencyKey): void {
                // 1. Debit balance atomically (throws RuntimeException if insufficient)
                $tx = $walletService->debit(
                    userId: $user->id,
                    amount: $validated['amount'],
                    description: "Retrait vers {$validated['payment_mode']} — {$validated['phone_number']}",
                );

                // 2. Initiate Fedapay payout (throws ApiError on failure → rolls back)
                $fedapayResult = $fedapayService->initiateWithdrawal(
                    amount: $validated['amount'],
                    mode: $validated['payment_mode'],
                    idempotencyKey: $idempotencyKey,
                    phoneData: [
                        'number'  => $validated['phone_number'],
                        'country' => $validated['phone_country'],
                    ],
                    user: $user,
                );

                // 3. Log financial event AFTER Fedapay succeeds — status=completed, fedapay_ref stored
                FinancialEvent::create([
                    'type'            => FinancialEventType::Withdrawal,
                    'booking_id'      => null,
                    'amount'          => $validated['amount'],
                    'fedapay_ref'     => (string) $fedapayResult['fedapay_payout_id'],
                    'idempotency_key' => $idempotencyKey,
                    'status'          => 'completed',
                    'metadata'        => [
                        'payment_mode'  => $validated['payment_mode'],
                        'phone_number'  => $validated['phone_number'],
                        'phone_country' => $validated['phone_country'],
                    ],
                ]);

                // 4. Mark wallet transaction as completed
                $tx->update(['status' => 'completed']);
            });
        } catch (\RuntimeException $e) {
            \Log::warning('Withdrawal insufficient balance', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Solde insuffisant.'], 422);
        } catch (\Exception $e) {
            \Log::error('Withdrawal failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage(), 'class' => get_class($e)]);
            return response()->json(['message' => 'Retrait échoué. Veuillez réessayer.'], 500);
        }

        return response()->json([
            'message' => 'Retrait initié avec succès.',
            'status'  => 'ok',
        ]);
    }
}
