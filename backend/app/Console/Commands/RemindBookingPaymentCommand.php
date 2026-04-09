<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RemindBookingPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:remind-payment';

    /**
     * The console command description.
     */
    protected $description = 'Send a payment reminder to producers for accepted bookings approaching the 24h expiry deadline';

    /**
     * Execute the console command.
     * Targets bookings accepted between 12h and 20h ago (reminder window), not yet reminded.
     */
    public function handle(): int
    {
        $from = now()->subHours(20);
        $to   = now()->subHours(12);

        $bookings = Booking::where('status', BookingStatus::Accepted->value)
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$from, $to])
            ->whereNull('payment_reminder_sent_at')
            ->get();

        $this->info("Found {$bookings->count()} booking(s) requiring a payment reminder.");

        $sent   = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $booking->loadMissing('face.userable');

                $faceName   = $booking->face?->userable?->display_name ?? 'La Face';
                $hoursLeft  = (int) max(0, 24 - now()->diffInHours($booking->accepted_at));

                Notification::create([
                    'user_id' => $booking->producer_id,
                    'type'    => 'booking_payment_reminder',
                    'data'    => [
                        'message'    => "Rappel : votre booking avec {$faceName} expire dans environ {$hoursLeft}h. Payez maintenant pour ne pas le perdre.",
                        'booking_id' => $booking->id,
                        'url'        => "/producer/bookings/{$booking->uuid}",
                    ],
                ]);

                $booking->update(['payment_reminder_sent_at' => now()]);

                $this->info("Reminder sent for booking #{$booking->id}");
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Booking payment reminder failed', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
                $this->error("Failed for booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
