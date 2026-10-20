<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\BookingShootingReminderMail;
use App\Mail\MissionShootingReminderMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindShootingDayCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:remind-shooting';

    /**
     * The console command description.
     */
    protected $description = 'Send shooting day reminders (J-1) to producers and selected faces for active missions and paid cash bookings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();

        // UGC (2.4) : pas de tournage — livrables à distance après expédition du produit.
        $missions = Mission::whereIn('status', [
            MissionStatus::Published->value,
            MissionStatus::Closed->value,
        ])
            ->where('type_mission', '!=', MissionType::Ugc->value)
            ->whereDate('date_tournage', $tomorrow)
            ->whereNull('shooting_reminder_sent_at')
            ->with([
                'producer',
                'candidatures' => fn ($q) => $q->whereIn('status', [
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                ])->with('face'),
            ])
            ->get();

        $this->info("Found {$missions->count()} mission(s) with shooting tomorrow.");

        $sent = 0;
        $failed = 0;

        foreach ($missions as $mission) {
            try {
                // Claim atomique : jamais deux rappels pour la même mission, même si deux exécutions se chevauchent.
                $claimedAt = now()->startOfSecond();
                $claimed = Mission::query()
                    ->whereKey($mission->id)
                    ->whereNull('shooting_reminder_sent_at')
                    ->update(['shooting_reminder_sent_at' => $claimedAt]);

                if ($claimed === 0) {
                    continue;
                }

                try {
                    $this->sendRemindersForMission($mission);
                } catch (\Throwable $e) {
                    // Échec avant fin d'envoi : on libère le claim pour que le prochain passage réessaie.
                    Mission::query()
                        ->whereKey($mission->id)
                        ->where('shooting_reminder_sent_at', $claimedAt)
                        ->update(['shooting_reminder_sent_at' => null]);

                    throw $e;
                }

                $this->info("Reminders sent for mission #{$mission->id} \"{$mission->titre}\"");
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('ShootingDay reminder failed', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for mission #{$mission->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        $this->remindBookings();

        return self::SUCCESS;
    }

    /**
     * Lot D6 : bookings cash payés dont le tournage (date_debut) a lieu demain, jour calendaire du
     * fuseau métier. In-app + e-mail aux deux parties, une seule fois par booking.
     */
    private function remindBookings(): void
    {
        // `date_debut` est une date calendaire sans heure (00:00 UTC) : on compare le jour, demain côté fuseau métier.
        $tomorrow = now()->setTimezone((string) config('app.business_timezone'))->addDay()->toDateString();

        // BINARY : aligne la comparaison SQL (collation _ci) sur le PHP `=== 'UGC'` (pas de tournage en UGC).
        $bookings = Booking::query()
            ->whereIn('status', [BookingStatus::Paid->value, BookingStatus::InProgress->value])
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereDate('date_debut', $tomorrow)
            ->whereNull('shooting_reminder_sent_at')
            ->with(['face.userable', 'producer.userable'])
            ->get();

        $this->info("Found {$bookings->count()} booking(s) with shooting tomorrow.");

        $sent = 0;

        foreach ($bookings as $booking) {
            try {
                // Claim atomique : jamais deux rappels pour le même booking, même si deux exécutions se chevauchent.
                $claimed = Booking::query()
                    ->whereKey($booking->id)
                    ->whereIn('status', [BookingStatus::Paid->value, BookingStatus::InProgress->value])
                    ->whereNull('shooting_reminder_sent_at')
                    ->update(['shooting_reminder_sent_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                $this->sendBookingReminders($booking);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('ShootingDay booking reminder failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Booking reminders sent: {$sent}.");
    }

    /** Chaque canal et chaque partie sont isolés : un échec n'empêche pas les autres envois. */
    private function sendBookingReminders(Booking $booking): void
    {
        foreach ([true, false] as $forProducer) {
            $user = $forProducer ? $booking->producer : $booking->face;
            $otherName = (string) data_get(
                $forProducer ? $booking->face : $booking->producer,
                'userable.display_name',
                $forProducer ? 'la Face' : 'le Producteur',
            );

            try {
                Notification::create([
                    'user_id' => $forProducer ? $booking->producer_id : $booking->face_id,
                    'type' => 'shooting_day_reminder',
                    'data' => [
                        'message' => "Rappel : votre tournage avec {$otherName} a lieu demain.",
                        'booking_id' => $booking->id,
                        'url' => ($forProducer ? '/producer' : '/face')."/bookings/{$booking->uuid}",
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('ShootingDay booking reminder notification failed', [
                    'booking_id' => $booking->id,
                    'for_producer' => $forProducer,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $email = trim((string) $user?->email);

                if ($email !== '') {
                    Mail::to($email)->queue(new BookingShootingReminderMail($booking, $forProducer));
                }
            } catch (\Throwable $e) {
                Log::warning('ShootingDay booking reminder email failed', [
                    'booking_id' => $booking->id,
                    'for_producer' => $forProducer,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** E-mail du rappel de mission (mêmes destinataires que l'in-app) : isolé, jamais bloquant. */
    private function mailMissionReminder(Mission $mission, ?int $userId, bool $forProducer): void
    {
        try {
            $email = $userId !== null ? trim((string) User::query()->whereKey($userId)->value('email')) : '';

            if ($email !== '') {
                Mail::to($email)->queue(new MissionShootingReminderMail($mission, $forProducer));
            }
        } catch (\Throwable $e) {
            Log::warning('ShootingDay mission reminder email failed', [
                'mission_id' => $mission->id,
                'for_producer' => $forProducer,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendRemindersForMission(Mission $mission): void
    {
        $producerUserId = User::where('userable_type', Producer::class)
            ->where('userable_id', $mission->producer_id)
            ->value('id');

        // Notify Producer
        if ($producerUserId) {
            Notification::create([
                'user_id' => $producerUserId,
                'type' => 'shooting_day_reminder',
                'data' => [
                    'message' => "Rappel : le tournage de votre mission \"{$mission->titre}\" a lieu demain.",
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->uuid}?tab=presences",
                ],
            ]);

            $this->mailMissionReminder($mission, (int) $producerUserId, true);
        }

        // Notify selected Faces
        foreach ($mission->candidatures as $candidature) {
            /** @var Candidature $candidature */
            $faceId = $candidature->face_id;
            $faceUserId = $faceId
                ? User::where('userable_type', \App\Models\Face::class)
                    ->where('userable_id', $faceId)
                    ->value('id')
                : null;

            if (! $faceUserId) {
                continue;
            }

            try {
                Notification::create([
                    'user_id' => $faceUserId,
                    'type' => 'shooting_day_reminder',
                    'data' => [
                        'message' => "Rappel : votre tournage pour la mission \"{$mission->titre}\" a lieu demain.",
                        'mission_id' => $mission->id,
                        'candidature_id' => $candidature->id,
                        'url' => "/face/missions/{$mission->uuid}", // /face/candidatures/{uuid} n'existe pas côté SPA
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('ShootingDay reminder for Face failed', [
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $this->mailMissionReminder($mission, (int) $faceUserId, false);
        }
    }
}
