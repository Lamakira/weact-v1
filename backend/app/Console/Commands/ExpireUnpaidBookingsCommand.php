<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpireUnpaidBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:expire-unpaid';

    /**
     * The console command description.
     */
    protected $description = 'Expire bookings in accepted status that have not been paid within 24 hours';

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
        $cutoff = now()->subHours(24);

        // Warn about accepted bookings with no accepted_at timestamp (pre-migration data).
        // SQL NULL comparisons are false, so these are excluded from the expiry query.
        $nullCount = Booking::where('status', BookingStatus::Accepted->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNull('accepted_at')
            ->count();

        if ($nullCount > 0) {
            $this->warn("{$nullCount} accepted booking(s) have no accepted_at timestamp and will not be expired. Backfill accepted_at to fix.");
        }

        // UGC (2.4) : aucun paiement cash n'est attendu après acceptation — le tunnel UGC ne doit pas expirer.
        // BINARY : aligne la comparaison SQL (collation _ci) sur le PHP `=== 'UGC'` — un type_contenu
        // libre « ugc » est un booking cash et doit rester couvert par le cron.
        $bookings = Booking::where('status', BookingStatus::Accepted->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNotNull('accepted_at')
            ->where('accepted_at', '<=', $cutoff)
            ->get();

        $this->info("Found {$bookings->count()} booking(s) to expire.");

        $expired = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $this->bookingService->expire($booking);
                $this->info("Expired booking #{$booking->id}");
                $expired++;
            } catch (\Throwable $e) {
                $this->error("Failed to expire booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Expired: {$expired}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
