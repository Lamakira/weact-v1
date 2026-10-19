<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingPaymentReminderMail;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $to = now()->subHours(12);

        // UGC (2.4) : pas de paiement Producteur après acceptation — aucune relance à envoyer.
        // BINARY : aligne la comparaison SQL (collation _ci) sur le PHP `=== 'UGC'` (cf. ExpireUnpaidBookings).
        $bookings = Booking::where('status', BookingStatus::Accepted->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNotNull('accepted_at')
            ->whereBetween('accepted_at', [$from, $to])
            ->whereNull('payment_reminder_sent_at')
            ->get();

        $this->info("Found {$bookings->count()} booking(s) requiring a payment reminder.");

        $sent = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            $claimedAt = null;

            try {
                // Claim atomique (idempotent si deux exécutions se chevauchent). Query builder
                // de base : updated_at du booking n'est pas touché. Une seule valeur sert au
                // claim et à sa libération (deux now() peuvent tomber sur deux secondes).
                $claimedAt = now()->startOfSecond();
                $claimed = Booking::query()
                    ->whereKey($booking->id)
                    ->where('status', BookingStatus::Accepted->value)
                    ->whereNull('payment_reminder_sent_at')
                    ->toBase()
                    ->update(['payment_reminder_sent_at' => $claimedAt]);

                if ($claimed === 0) {
                    $claimedAt = null;

                    continue;
                }

                $booking->loadMissing(['face.userable', 'producer']);

                $faceName = data_get($booking, 'face.userable.display_name');
                // Heures restantes jusqu'à l'échéance exacte (accepted_at + 24 h), jamais négatif.
                $deadline = $booking->paymentDeadline();
                $hoursLeft = $deadline === null ? 0 : (int) max(0, round(now()->diffInHours($deadline, false)));

                Notification::create([
                    'user_id' => $booking->producer_id,
                    'type' => 'booking_payment_reminder',
                    'data' => [
                        'message' => 'Rappel : votre booking avec '.(is_string($faceName) && $faceName !== '' ? $faceName : 'La Face')." expire dans environ {$hoursLeft}h. Payez maintenant pour ne pas le perdre.",
                        'booking_id' => $booking->id,
                        'url' => "/producer/bookings/{$booking->uuid}",
                    ],
                ]);

                // Lot B11 : la relance part aussi par e-mail (non-fatal : l'in-app est déjà posée).
                try {
                    $producerEmail = trim((string) $booking->producer?->email);
                    if ($producerEmail !== '') {
                        Mail::to($producerEmail)->queue(new BookingPaymentReminderMail($booking));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Booking payment reminder email failed', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->info("Reminder sent for booking #{$booking->id}");
                $sent++;
            } catch (\Throwable $e) {
                // La notification in-app n'a pas pu être posée : on libère la relance pour
                // que le prochain passage réessaie.
                if ($claimedAt !== null) {
                    Booking::query()
                        ->whereKey($booking->id)
                        ->where('payment_reminder_sent_at', $claimedAt)
                        ->toBase()
                        ->update(['payment_reminder_sent_at' => null]);
                }

                Log::warning('Booking payment reminder failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
