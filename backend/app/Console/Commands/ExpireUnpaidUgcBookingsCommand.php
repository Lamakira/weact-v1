<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

/**
 * Lot B10 : une demande UGC dont la commission n'est pas réglée 48 h après sa création
 * expire ; les deux parties sont prévenues (BookingExpired). Aucun argent engagé.
 */
class ExpireUnpaidUgcBookingsCommand extends Command
{
    protected $signature = 'bookings:expire-unpaid-ugc';

    protected $description = 'Expire UGC booking requests whose commission is still unpaid 48 h after creation';

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // BINARY : aligne SQL (collation _ci) sur le PHP `=== 'UGC'` (match positif).
        $bookings = Booking::where('status', BookingStatus::Pending->value)
            ->whereRaw("BINARY type_contenu = 'UGC'")
            ->whereNull('commission_paid_at')
            ->where('created_at', '<=', now()->subHours(Booking::UGC_COMMISSION_PAYMENT_WINDOW_HOURS))
            ->get();

        $this->info("Found {$bookings->count()} unpaid UGC booking(s) to expire.");

        $expired = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                if ($this->bookingService->expireUnpaidUgc($booking)) {
                    $this->info("Expired UGC booking #{$booking->id}");
                    $expired++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to expire UGC booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Expired: {$expired}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
