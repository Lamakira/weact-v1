<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Mail\FaceConfirmationReminderMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use App\Services\MissionPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot B3 — relance unique d'une Face sélectionnée (mission cash payée) qui n'a pas encore confirmé
 * sa participation, quand il reste 24 h ou moins avant l'échéance (cf. Candidature::confirmationDeadline).
 */
class RemindUnconfirmedFacesCommand extends Command
{
    protected $signature = 'missions:remind-unconfirmed-faces';

    protected $description = 'Remind a selected Face who has not confirmed her participation when 24 hours or less remain before her place is freed.';

    public function handle(): int
    {
        $now = now();
        $horizon = $now->copy()->addHours(MissionPaymentService::FACE_CONFIRMATION_REMINDER_HOURS);

        $candidatures = Candidature::query()
            ->where('status', CandidatureStatus::Accepted->value)
            ->whereNotNull('accepted_at')
            ->whereNull('confirmation_reminder_sent_at')
            ->whereHas('mission', fn ($query) => $query->where('status', MissionStatus::Closed->value))
            ->whereHas('paymentEntry', fn ($query) => $query
                ->whereNotNull('mission_payment_id')
                ->where('escrow_status', EscrowStatus::Locked->value))
            ->with(['mission', 'face'])
            ->get()
            ->filter(function (Candidature $candidature) use ($now, $horizon): bool {
                $deadline = $candidature->confirmationDeadline();

                return $deadline !== null && $deadline->greaterThan($now) && $deadline->lessThanOrEqualTo($horizon);
            });

        $this->info("Found {$candidatures->count()} unconfirmed Face(s) requiring a reminder.");

        $sent = 0;
        $failed = 0;

        foreach ($candidatures as $candidature) {
            try {
                // Claim atomique : idempotent en cas de chevauchement. Une seule valeur (à la seconde)
                // sert au claim ET à sa libération.
                $claimedAt = now()->startOfSecond();
                $claimed = Candidature::query()
                    ->whereKey($candidature->id)
                    ->where('status', CandidatureStatus::Accepted->value)
                    ->whereNull('confirmation_reminder_sent_at')
                    ->update(['confirmation_reminder_sent_at' => $claimedAt]);

                if ($claimed === 0) {
                    continue;
                }

                if (! $this->notifyFace($candidature)) {
                    // Face injoignable (notification ET email en échec) : on libère la relance.
                    Candidature::query()
                        ->whereKey($candidature->id)
                        ->where('confirmation_reminder_sent_at', $claimedAt)
                        ->update(['confirmation_reminder_sent_at' => null]);

                    Log::warning('Face confirmation reminder released: Face could not be notified', [
                        'candidature_id' => $candidature->id,
                    ]);
                    $this->warn("Reminder released for candidature #{$candidature->id}: Face not notified, will retry.");
                    $failed++;

                    continue;
                }

                $sent++;
                $this->info("Reminder sent for candidature #{$candidature->id}");
            } catch (\Throwable $e) {
                Log::warning('Face confirmation reminder failed', [
                    'candidature_id' => $candidature->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for candidature #{$candidature->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Retourne false si AUCUN des deux canaux (in-app, email) n'a abouti.
     */
    private function notifyFace(Candidature $candidature): bool
    {
        /** @var Mission $mission */
        $mission = $candidature->mission;
        /** @var Face|null $face */
        $face = $candidature->face;
        $deadlineAt = $candidature->confirmationDeadline();

        if ($face === null || $deadlineAt === null) {
            return false;
        }

        $deadline = Booking::formatForBusiness($deadlineAt, 'd/m \à H\hi');

        /** @var User|null $user */
        $user = User::where('userable_type', Face::class)
            ->where('userable_id', $face->id)
            ->first();

        if ($user === null) {
            return false;
        }

        $notified = false;

        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'mission_confirmation_reminder',
                'data' => [
                    'message' => "Confirmez votre participation à « {$mission->titre} » avant le {$deadline}, sinon votre place sera libérée.",
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'url' => '/face/candidatures',
                ],
            ]);
            $notified = true;
        } catch (\Throwable $e) {
            Log::warning('Face confirmation reminder notification failed', [
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $email = trim((string) $user->email);

            if ($email !== '') {
                Mail::to($email)->queue(new FaceConfirmationReminderMail($face, $mission, $deadline));
                $notified = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Face confirmation reminder email queue failed', [
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notified;
    }
}
