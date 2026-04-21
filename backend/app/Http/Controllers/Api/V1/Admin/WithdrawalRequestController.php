<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCodes;
use App\Enums\FinancialEventType;
use App\Http\Controllers\Controller;
use App\Mail\WithdrawalApprovedMail;
use App\Mail\WithdrawalRejectedMail;
use App\Models\Admin;
use App\Models\FinancialEvent;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WithdrawalRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $query = WithdrawalRequest::query()
            ->with(['user.userable'])
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $withdrawalRequests = $query->paginate(20)->through(
            fn (WithdrawalRequest $withdrawalRequest): array => [
                'id' => $withdrawalRequest->id,
                'amount' => (int) $withdrawalRequest->amount,
                'payment_mode' => $withdrawalRequest->payment_mode,
                'phone_number' => $withdrawalRequest->phone_number,
                'phone_country' => $withdrawalRequest->phone_country,
                'status' => $withdrawalRequest->status,
                'notes' => $withdrawalRequest->notes,
                'processed_at' => $withdrawalRequest->processed_at?->toIso8601String(),
                'created_at' => $withdrawalRequest->created_at?->toIso8601String(),
                'user_email' => $withdrawalRequest->user->email,
                'user_name' => data_get($withdrawalRequest->user, 'userable.display_name'),
            ],
        );

        return response()->json([
            'data' => $withdrawalRequests->items(),
            'meta' => [
                'current_page' => $withdrawalRequests->currentPage(),
                'last_page' => $withdrawalRequests->lastPage(),
                'per_page' => $withdrawalRequests->perPage(),
                'total' => $withdrawalRequests->total(),
            ],
            'message' => 'Withdrawal requests retrieved successfully',
        ]);
    }

    public function approve(
        WithdrawalRequest $withdrawalRequest,
        Request $request,
        WalletService $walletService,
        WithdrawalService $withdrawalService,
    ): JsonResponse {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user();

        try {
            $processedRequest = DB::transaction(function () use (
                $withdrawalRequest,
                $walletService,
                $withdrawalService,
                $validated,
                $admin,
            ): WithdrawalRequest {
                $lockedRequest = WithdrawalRequest::query()
                    ->with('user.userable')
                    ->lockForUpdate()
                    ->findOrFail($withdrawalRequest->id);

                if (! $lockedRequest->isPending()) {
                    throw ValidationException::withMessages([
                        'status' => ['Cette demande a deja ete traitee.'],
                    ]);
                }

                $walletTransaction = $walletService->debit(
                    userId: $lockedRequest->user_id,
                    amount: (int) $lockedRequest->amount,
                    description: $withdrawalService->buildDescription(
                        $lockedRequest->payment_mode,
                        $lockedRequest->phone_number,
                        true,
                    ),
                    bookingId: null,
                    status: 'completed',
                );

                $lockedRequest->update([
                    'status' => 'approved',
                    'notes' => $validated['notes'] ?? null,
                    'wallet_transaction_id' => $walletTransaction->id,
                    'processed_by_admin_id' => $admin->id,
                    'processed_at' => now(),
                ]);

                FinancialEvent::create([
                    'type' => FinancialEventType::Withdrawal,
                    'booking_id' => null,
                    'amount' => $lockedRequest->amount,
                    'fedapay_ref' => null,
                    'idempotency_key' => "manual_withdrawal_{$lockedRequest->id}_approved",
                    'status' => 'completed',
                    'metadata' => [
                        'withdrawal_request_id' => $lockedRequest->id,
                        'wallet_transaction_id' => $walletTransaction->id,
                        'user_id' => $lockedRequest->user_id,
                        'payment_mode' => $lockedRequest->payment_mode,
                        'phone_number' => $lockedRequest->phone_number,
                        'phone_country' => $lockedRequest->phone_country,
                        'processed_by_admin_id' => $admin->id,
                    ],
                ]);

                return $lockedRequest->fresh(['user.userable', 'walletTransaction']);
            });
        } catch (RuntimeException $e) {
            return response()->json(
                ErrorCodes::InsufficientBalance->envelope('Solde insuffisant au moment de l’approbation. L’utilisateur a probablement dépensé une partie de ses fonds.'),
                422
            );
        }

        Mail::to($processedRequest->user->email)->send(new WithdrawalApprovedMail($processedRequest));

        return response()->json([
            'data' => [
                'id' => $processedRequest->id,
                'status' => $processedRequest->status,
            ],
            'message' => 'Withdrawal request approved successfully',
        ]);
    }

    public function reject(WithdrawalRequest $withdrawalRequest, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        /** @var Admin $admin */
        $admin = $request->user();

        $processedRequest = DB::transaction(function () use ($withdrawalRequest, $validated, $admin): WithdrawalRequest {
            $lockedRequest = WithdrawalRequest::query()
                ->with('user.userable')
                ->lockForUpdate()
                ->findOrFail($withdrawalRequest->id);

            if (! $lockedRequest->isPending()) {
                throw ValidationException::withMessages([
                    'status' => ['Cette demande a deja ete traitee.'],
                ]);
            }

            $lockedRequest->update([
                'status' => 'rejected',
                'notes' => $validated['notes'],
                'processed_by_admin_id' => $admin->id,
                'processed_at' => now(),
            ]);

            return $lockedRequest->fresh(['user.userable']);
        });

        Mail::to($processedRequest->user->email)->send(new WithdrawalRejectedMail($processedRequest));

        return response()->json([
            'data' => [
                'id' => $processedRequest->id,
                'status' => $processedRequest->status,
            ],
            'message' => 'Withdrawal request rejected successfully',
        ]);
    }
}
