<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireUnacceptedBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:expire-unaccepted';

    /**
     * The console command description.
     */
    protected $description = 'Expire pending bookings whose shooting date has passed without a response';

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
        $cutoff = now();

        $bookings = Booking::where('status', BookingStatus::Pending->value)
            ->where('date_debut', '<=', $cutoff)
            ->get();

        $this->info("Found {$bookings->count()} booking(s) to expire.");

        $expired = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $this->bookingService->expireUnaccepted($booking);
                $booking->refresh();

                if ($booking->status === BookingStatus::Expired) {
                    $this->info("Expired booking #{$booking->id}");
                    $expired++;

                    continue;
                }

                $this->warn("Skipped booking #{$booking->id}");
                $skipped++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Expired: {$expired}, Skipped: {$skipped}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
