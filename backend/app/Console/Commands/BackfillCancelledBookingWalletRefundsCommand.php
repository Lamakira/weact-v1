<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Models\Booking;
use App\Models\FinancialEvent;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillCancelledBookingWalletRefundsCommand extends Command
{
    use RecordsFinancialEvent;

    protected $signature = 'bookings:backfill-cancelled-wallet-refunds
        {--booking-id= : Restrict the backfill to a single booking ID}
        {--chunk=200 : Number of bookings to process per chunk}
        {--dry-run : Show impacted bookings without writing changes}';

    protected $description = 'Backfill missing Producer wallet refunds for already-cancelled paid bookings';

    public function handle(WalletService $walletService): int
    {
        $bookingId = $this->option('booking-id');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $query = Booking::query()
            ->whereIn('status', [
                BookingStatus::CancelledByProducer,
                BookingStatus::CancelledByFace,
            ])
            ->whereNotNull('fedapay_transaction_id')
            ->orderBy('id');

        if ($bookingId !== null) {
            $query->whereKey((int) $bookingId);
        }

        $processed = 0;
        $credited = 0;
        $skipped = 0;
        $warnings = 0;

        $query->chunkById($chunkSize, function ($bookings) use (
            $walletService,
            $dryRun,
            &$processed,
            &$credited,
            &$skipped,
            &$warnings
        ): void {
            foreach ($bookings as $booking) {
                $processed++;

                $result = DB::transaction(function () use ($booking, $walletService, $dryRun): array {
                    /** @var Booking $lockedBooking */
                    $lockedBooking = Booking::query()
                        ->with('escrowTransaction')
                        ->lockForUpdate()
                        ->findOrFail($booking->id);

                    $expectedRefund = (int) round($lockedBooking->montant_total_producteur * 0.90);
                    $existingWalletCredit = (int) WalletTransaction::query()
                        ->where('booking_id', $lockedBooking->id)
                        ->where('user_id', $lockedBooking->producer_id)
                        ->where('type', 'credit')
                        ->sum('amount');

                    $missingAmount = $expectedRefund - $existingWalletCredit;

                    if ($missingAmount <= 0) {
                        return [
                            'action' => 'skip',
                            'reason' => 'already_credited',
                            'booking_id' => $lockedBooking->id,
                        ];
                    }

                    $escrow = $lockedBooking->escrowTransaction;
                    if ($escrow === null) {
                        return [
                            'action' => 'warning',
                            'reason' => 'missing_escrow',
                            'booking_id' => $lockedBooking->id,
                            'missing_amount' => $missingAmount,
                        ];
                    }

                    if ($dryRun) {
                        return [
                            'action' => 'dry-run',
                            'booking_id' => $lockedBooking->id,
                            'missing_amount' => $missingAmount,
                            'existing_wallet_credit' => $existingWalletCredit,
                        ];
                    }

                    $escrow->update([
                        'status' => EscrowStatus::Refunded->value,
                        'fedapay_ref' => null,
                        'refunded_at' => $escrow->refunded_at ?? now(),
                    ]);

                    $walletService->credit(
                        userId: $lockedBooking->producer_id,
                        amount: $missingAmount,
                        booking: $lockedBooking,
                        description: "Booking : rattrapage remboursement annulation",
                    );

                    $hasCompletedRefundEvent = FinancialEvent::query()
                        ->where('booking_id', $lockedBooking->id)
                        ->where('type', FinancialEventType::Refund)
                        ->where('status', 'completed')
                        ->exists();

                    if (! $hasCompletedRefundEvent) {
                        $this->recordFinancialEvent(
                            FinancialEventType::Refund,
                            $lockedBooking,
                            $missingAmount,
                            [
                                'status' => 'completed',
                                'idempotency_key' => "backfill-refund-booking-{$lockedBooking->id}",
                                'metadata' => [
                                    'refund_channel' => 'wallet',
                                    'refund_percentage' => 90,
                                    'retained_amount' => $lockedBooking->montant_total_producteur - $expectedRefund,
                                    'backfill' => true,
                                    'existing_wallet_credit_before' => $existingWalletCredit,
                                    'expected_refund_amount' => $expectedRefund,
                                ],
                            ],
                        );
                    }

                    return [
                        'action' => 'credited',
                        'booking_id' => $lockedBooking->id,
                        'credited_amount' => $missingAmount,
                        'existing_wallet_credit' => $existingWalletCredit,
                    ];
                });

                match ($result['action']) {
                    'credited' => $this->info("Booking #{$result['booking_id']} backfilled: +{$result['credited_amount']} XOF"),
                    'dry-run' => $this->line("DRY RUN booking #{$result['booking_id']}: missing {$result['missing_amount']} XOF"),
                    'warning' => $this->warn("Booking #{$result['booking_id']} skipped: missing escrow"),
                    default => $this->line("Booking #{$result['booking_id']} skipped: already credited"),
                };

                if ($result['action'] === 'credited') {
                    $credited++;
                } elseif ($result['action'] === 'warning') {
                    $warnings++;
                    Log::warning('Cancelled booking wallet refund backfill skipped because escrow is missing', $result);
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Processed: {$processed}, credited: {$credited}, skipped: {$skipped}, warnings: {$warnings}.");

        return $warnings > 0 ? self::FAILURE : self::SUCCESS;
    }
}
