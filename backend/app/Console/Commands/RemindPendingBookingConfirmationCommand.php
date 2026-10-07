<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingCompletionReminderMail;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindPendingBookingConfirmationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:remind-pending-confirmation';

    /**
     * The console command description.
     */
    protected $description = 'Remind both parties of a Paid booking nobody confirmed nor reported, 24h after the shoot day ends (auto-completion follows 7 days after).';

    /**
     * Execute the console command.
     * Legacy bookings (date_fin older than 30 days) are never reminded.
     */
    public function handle(): int
    {
        // date_fin startOfDay + 2 jours <= now  <=>  date_fin < startOfDay(now - 2 jours) + 1 jour
        $dueBefore = now()->subDays(BookingService::REMINDER_AFTER_DAYS)->startOfDay()->addDay();

        // BINARY : aligne la comparaison SQL (collation _ci) sur le PHP `=== 'UGC'`.
        $bookings = Booking::query()
            ->where('status', BookingStatus::Paid->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNull('completion_reminder_sent_at')
            ->whereNull('settlement_due_at')
            // Fenêtre de relance = non-legacy (voir Booking::isLegacyForAutoPayment, même seuil de 30 jours).
            ->where('date_fin', '>=', now()->subDays(BookingService::LEGACY_AFTER_DAYS))
            ->where('date_fin', '<', $dueBefore)
            ->get()
            ->reject(fn (Booking $booking): bool => $booking->isLegacyForAutoPayment());

        $this->info("Found {$bookings->count()} booking(s) requiring a confirmation reminder.");

        $sent = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                // Claim atomique : reste idempotent en cas de chevauchement d'exécutions.
                $claimed = Booking::query()
                    ->whereKey($booking->id)
                    ->where('status', BookingStatus::Paid->value)
                    ->whereNull('completion_reminder_sent_at')
                    ->update(['completion_reminder_sent_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                // L'échéance affichée dépend de l'heure réelle de la relance (rappel + 6 jours minimum).
                $claimedAt = now();
                $booking->completion_reminder_sent_at = $claimedAt;

                if (! $this->notifyParties($booking)) {
                    // Producteur injoignable (notification ET email en échec) : on libère la relance
                    // pour que le prochain passage réessaie, sinon la Face serait payée sans qu'il ait été prévenu.
                    Booking::query()
                        ->whereKey($booking->id)
                        ->where('status', BookingStatus::Paid->value)
                        ->where('completion_reminder_sent_at', $claimedAt)
                        ->update(['completion_reminder_sent_at' => null]);

                    Log::warning('Booking completion reminder released: Producer could not be notified', [
                        'booking_id' => $booking->id,
                    ]);
                    $this->warn("Reminder released for booking #{$booking->id}: Producer not notified, will retry.");
                    $failed++;

                    continue;
                }

                $sent++;
                $this->info("Reminder sent for booking #{$booking->id}");
            } catch (\Throwable $e) {
                Log::warning('Booking completion reminder failed', [
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

    /**
     * Le Producteur est prévenu en premier : retourne false si AUCUN de ses deux canaux n'a abouti.
     */
    private function notifyParties(Booking $booking): bool
    {
        $booking->loadMissing('face.userable', 'producer.userable');

        $dueAt = $booking->silentAutoCompleteDueAt();
        $autoDate = $dueAt !== null ? Booking::formatForBusiness($dueAt, 'd/m/Y') : '';
        $producerNotified = false;

        try {
            Notification::create([
                'user_id' => $booking->producer_id,
                'type' => 'booking_completion_reminder',
                'data' => [
                    'message' => "Confirmez la prestation ou signalez une absence avant le {$autoDate}, sinon la Face sera payée automatiquement.",
                    'booking_id' => $booking->id,
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ]);
            $producerNotified = true;
        } catch (\Throwable $e) {
            Log::warning('Booking completion reminder (Producer) notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $producerEmail = trim((string) $booking->producer?->email);

            if ($producerEmail !== '') {
                Mail::to($producerEmail)->queue(new BookingCompletionReminderMail($booking, $autoDate));
                $producerNotified = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Booking completion reminder email queue failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $producerNotified) {
            return false;
        }

        try {
            Notification::create([
                'user_id' => $booking->face_id,
                'type' => 'booking_completion_reminder',
                'data' => [
                    'message' => 'Confirmez la fin de votre prestation.',
                    'booking_id' => $booking->id,
                    'url' => "/face/bookings/{$booking->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Booking completion reminder (Face) notification failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
