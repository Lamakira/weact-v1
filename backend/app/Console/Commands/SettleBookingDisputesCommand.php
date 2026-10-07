<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SettleBookingDisputesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:settle-disputes';

    /**
     * The console command description.
     */
    protected $description = 'Auto-settle no-show / late-cancellation bookings (refund the Producer) once the 72h Face dispute window expires without contestation.';

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::NoShow->value, BookingStatus::CancelledByProducer->value])
            ->whereNotNull('settlement_due_at')
            ->where('settlement_due_at', '<=', now())
            ->whereNull('disputed_at')
            ->whereNull('dispute_resolved_at')
            ->whereHas('escrowTransaction', fn ($query) => $query->where('status', EscrowStatus::Locked->value))
            ->get();

        $this->info("Found {$bookings->count()} booking(s) to auto-settle.");

        $settled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $result = $this->bookingService->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);

                if ($result === null) {
                    $skipped++;

                    continue;
                }

                $this->info("Auto-settled booking #{$booking->id}: Producer refunded.");
                $settled++;
            } catch (\Throwable $e) {
                Log::error('Booking dispute auto-settlement failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to auto-settle booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Settled: {$settled}, Skipped: {$skipped}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
