<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Models\EscrowTransaction;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

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
}
