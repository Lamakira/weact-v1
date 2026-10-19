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
    protected $description = 'Expire pending cash bookings the Face did not answer in time (72 h after creation, or 08:00 on the shooting day if earlier)';

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
        // Sur-ensemble SQL borné (créée il y a 72 h ou plus, ou tournage d'ici un jour : 08 h
        // en fuseau métier le jour du tournage suit date_debut (minuit) de quelques heures seulement), puis
        // échéance exacte en PHP — Booking::faceResponseDeadline() reste la source unique.
        // BINARY : aligne SQL (collation _ci) sur le PHP `=== 'UGC'` ; UGC exclu (fenêtre dédiée).
        $bookings = Booking::where('status', BookingStatus::Pending->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->where(function ($query): void {
                $query->where('created_at', '<=', now()->subHours(Booking::FACE_RESPONSE_WINDOW_HOURS))
                    ->orWhere('date_debut', '<=', now()->addDay());
            })
            ->get()
            ->filter(function (Booking $booking): bool {
                $deadline = $booking->faceResponseDeadline();

                return $deadline !== null && now()->gte($deadline);
            })
            ->values();

        $this->info("Found {$bookings->count()} booking(s) to expire.");

        $expired = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $this->bookingService->expireUnaccepted($booking);
                $booking->refresh();

                $status = BookingStatus::from((string) $booking->getRawOriginal('status'));

                if ($status === BookingStatus::Expired) {
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
