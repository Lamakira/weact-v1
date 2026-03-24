<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\FedapayService;
use Illuminate\Http\JsonResponse;

class AdminFinanceController extends Controller
{
    /**
     * Return a financial overview of the platform.
     *
     * Three sections:
     * - retirable    : total face wallet balances (can be withdrawn now)
     * - faces        : wallet balances + all locked escrow (booking + mission)
     * - platform     : commissions earned from completed bookings + paid missions
     *
     * Plus volume and count metrics.
     */
    public function overview(FedapayService $fedapayService): JsonResponse
    {
        $fedapayBalance = $fedapayService->getBalanceSummary();

        // ── What faces can withdraw right now ────────────────────────────────
        $walletBalance = (int) User::where('userable_type', Face::class)->sum('balance');

        // ── Escrow still in flight ───────────────────────────────────────────
        $bookingEscrowLocked = (int) EscrowTransaction::where('status', 'locked')->sum('amount');
        $missionEscrowLocked = (int) MissionPaymentCandidature::where('escrow_status', EscrowStatus::Locked)->sum('montant_face_recoit');
        $totalEscrow = $bookingEscrowLocked + $missionEscrowLocked;

        // ── Platform revenue (commissions) ───────────────────────────────────
        $bookingCommissions = (int) Booking::where('status', BookingStatus::Completed)
            ->selectRaw('SUM(montant_total_producteur - montant_face_recoit) as total')
            ->value('total');

        $missionCommissions = (int) MissionPayment::where('status', MissionPaymentStatus::Paid)
            ->selectRaw('SUM(commission_producteur + commission_faces_total) as total')
            ->value('total');

        $totalPlatformRevenue = $bookingCommissions + $missionCommissions;

        // ── Volume transacted ────────────────────────────────────────────────
        $bookingVolume = (int) Booking::where('status', BookingStatus::Completed)->sum('montant_total_producteur');
        $missionVolume = (int) MissionPayment::where('status', MissionPaymentStatus::Paid)->sum('montant_total_producteur');

        // ── Withdrawals ──────────────────────────────────────────────────────
        $totalWithdrawals = (int) WalletTransaction::where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');

        $withdrawalsCount = WalletTransaction::where('type', 'debit')
            ->where('status', 'completed')
            ->count();

        return response()->json([
            'data' => [
                'retirable' => [
                    'amount' => $walletBalance,
                    'label'  => 'Soldes wallets des Faces',
                ],
                'faces' => [
                    'amount'               => $walletBalance + $totalEscrow,
                    'wallet_balance'       => $walletBalance,
                    'escrow_booking'       => $bookingEscrowLocked,
                    'escrow_mission'       => $missionEscrowLocked,
                    'label'                => 'Total appartenant aux Faces',
                ],
                'platform' => [
                    'amount'               => $totalPlatformRevenue,
                    'from_bookings'        => $bookingCommissions,
                    'from_missions'        => $missionCommissions,
                    'label'                => 'Revenus plateforme (commissions)',
                ],
                'volume' => [
                    'bookings' => $bookingVolume,
                    'missions' => $missionVolume,
                    'total'    => $bookingVolume + $missionVolume,
                ],
                'withdrawals' => [
                    'total_amount' => $totalWithdrawals,
                    'count'        => $withdrawalsCount,
                ],
                'withdrawal_requests' => [
                    'pending_count' => WithdrawalRequest::where('status', 'pending')->count(),
                    'pending_amount' => (int) WithdrawalRequest::where('status', 'pending')->sum('amount'),
                ],
                'fedapay_balance' => $fedapayBalance,
            ],
            'message' => 'Financial overview retrieved successfully',
        ]);
    }

    /**
     * Return the 20 most recent completed withdrawals.
     */
    public function recentWithdrawals(): JsonResponse
    {
        $withdrawals = WalletTransaction::where('type', 'debit')
            ->where('status', 'completed')
            ->with('user:id,email,userable_type,userable_id')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id'          => $tx->id,
                'amount'      => $tx->amount,
                'description' => $tx->description,
                'reference'   => $tx->reference,
                'created_at'  => $tx->created_at?->toIso8601String(),
                'user_email'  => $tx->user?->email,
            ]);

        return response()->json([
            'data'    => $withdrawals,
            'message' => 'Recent withdrawals retrieved successfully',
        ]);
    }
}
