<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class AutoCompleteBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:auto-complete';

    /**
     * The console command description.
     */
    protected $description = 'Auto-complete bookings where 72 hours have elapsed since date_fin without mutual confirmation';

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
        $cutoff = now()->subHours(72);

        $bookings = Booking::whereIn('status', [
            BookingStatus::ConfirmedByFace->value,
            BookingStatus::ConfirmedByProducer->value,
        ])
            ->where('date_fin', '<=', $cutoff)
            ->get();

        $this->info("Found {$bookings->count()} booking(s) to auto-complete.");

        $completed = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $this->bookingService->autoComplete($booking);
                $this->info("Auto-completed booking #{$booking->id}");
                $completed++;
            } catch (\Throwable $e) {
                $this->error("Failed to auto-complete booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Completed: {$completed}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
