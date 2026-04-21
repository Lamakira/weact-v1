<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ErrorCodes;
use App\Exceptions\WithdrawalLockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\WithdrawWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): WalletResource
    {
        $user = $request->user();

        /** @var int $pendingEscrow */
        $pendingEscrow = ($user->userable_type === Face::class)
            ? (int) EscrowTransaction::whereHas('booking', function ($query) use ($user): void {
                $query->where('face_id', $user->id);
            })
                ->where('status', 'locked')
                ->sum('amount')
            : 0;

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        $withdrawalRequests = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        return new WalletResource([
            'balance' => (int) $user->balance,
            'pending_escrow' => $pendingEscrow,
            'transactions' => $transactions,
            'withdrawal_requests' => $withdrawalRequests,
        ]);
    }

    public function withdraw(
        WithdrawWalletRequest $request,
        WithdrawalService $withdrawalService,
    ): JsonResponse {
        try {
            $result = $withdrawalService->initiate($request->user(), $request->validated());
        } catch (WithdrawalLockException $e) {
            \Log::warning('Withdrawal lock conflict', ['user_id' => $request->user()->id]);

            return response()->json(ErrorCodes::WithdrawalLock->envelope($e->getMessage()), 409);
        } catch (\RuntimeException $e) {
            \Log::warning('Withdrawal insufficient balance', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return response()->json(ErrorCodes::InsufficientBalance->envelope('Solde insuffisant.'), 422);
        } catch (\Exception $e) {
            \Log::error('Withdrawal failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage(), 'class' => get_class($e)]);

            return response()->json(ErrorCodes::WithdrawalFailed->envelope('Retrait échoué. Veuillez réessayer.'), 500);
        }

        return response()->json([
            'message' => $result['message'],
            'status' => 'ok',
            'withdrawal_mode' => $result['mode'],
        ]);
    }
}
