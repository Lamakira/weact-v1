<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\BookingResponseReminderMail;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot B10 : relance unique de la Face (in-app + e-mail) quand il lui reste 24 h ou moins pour
 * répondre à une demande cash (soit 48 h après la demande pour une fenêtre pleine de 72 h).
 * Pas de relance quand la fenêtre entière fait 24 h ou moins : la notification de réception
 * annonce déjà l'échéance. Idempotente par bookings.response_reminder_sent_at.
 */
class RemindFaceBookingResponseCommand extends Command
{
    protected $signature = 'bookings:remind-face-response';

    protected $description = 'Remind the Face once when 24 h or less remain to answer a pending cash booking request';

    public function handle(): int
    {
        // Sur-ensemble SQL borné (créée il y a 48 h ou plus, ou tournage d'ici deux jours),
        // échéance exacte en PHP via Booking::faceResponseDeadline(). BINARY : cf. expire-unaccepted.
        $bookings = Booking::where('status', BookingStatus::Pending->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNull('response_reminder_sent_at')
            ->where(function ($query): void {
                $query->where('created_at', '<=', now()->subHours(Booking::FACE_RESPONSE_WINDOW_HOURS - Booking::FACE_RESPONSE_REMINDER_BEFORE_HOURS))
                    ->orWhere('date_debut', '<=', now()->addDays(2));
            })
            ->get()
            ->filter(fn (Booking $booking): bool => $this->isDueForReminder($booking))
            ->values();

        $this->info("Found {$bookings->count()} booking(s) requiring a response reminder.");

        $sent = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                // Réservation atomique : un second passage concurrent ne renvoie rien.
                $claimed = Booking::query()
                    ->whereKey($booking->id)
                    ->where('status', BookingStatus::Pending->value)
                    ->whereNull('response_reminder_sent_at')
                    ->update(['response_reminder_sent_at' => now()]);

                if ($claimed !== 1) {
                    continue;
                }

                $booking->loadMissing(['producer.userable', 'face.userable']);
                $producerName = (string) data_get($booking, 'producer.userable.display_name', 'Le Producteur');
                $deadlineLabel = (string) $booking->faceResponseDeadlineLabel();

                Notification::create([
                    'user_id' => $booking->face_id,
                    'type' => 'booking_response_reminder',
                    'data' => [
                        'message' => "Rappel : {$producerName} attend votre réponse à sa demande de booking. Répondez avant le {$deadlineLabel}, sinon la demande expire.",
                        'booking_id' => $booking->id,
                        'url' => "/face/bookings/{$booking->uuid}",
                    ],
                ]);

                $faceEmail = trim((string) $booking->face?->email);
                if ($faceEmail !== '') {
                    Mail::to($faceEmail)->queue(new BookingResponseReminderMail($booking));
                }

                $this->info("Reminder sent for booking #{$booking->id}");
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Booking response reminder failed', [
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

    private function isDueForReminder(Booking $booking): bool
    {
        $deadline = $booking->faceResponseDeadline();

        if ($deadline === null || $booking->created_at === null || now()->gte($deadline)) {
            return false;
        }

        // Fenêtre entière de 24 h ou moins : l'échéance figure déjà dans la notification de réception.
        if ($booking->created_at->diffInHours($deadline) <= Booking::FACE_RESPONSE_REMINDER_BEFORE_HOURS) {
            return false;
        }

        return now()->gte($deadline->copy()->subHours(Booking::FACE_RESPONSE_REMINDER_BEFORE_HOURS));
    }
}
