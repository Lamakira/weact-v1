<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Mail\MissionPaymentReminderMail;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindUnpaidMissionSelectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:remind-unpaid-selections';

    /**
     * The console command description.
     */
    protected $description = 'Remind the Producer of a mission selection still unpaid 12 to 20 hours after the confirmation (cancelled automatically after 24h).';

    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $payments = MissionPayment::query()
            ->where('status', MissionPaymentStatus::Pending->value)
            ->whereNull('reminder_sent_at')
            ->where('created_at', '>=', now()->subHours(MissionPaymentService::SELECTION_REMINDER_MAX_AGE_HOURS))
            ->where('created_at', '<=', now()->subHours(MissionPaymentService::SELECTION_REMINDER_MIN_AGE_HOURS))
            ->whereHas('mission', fn ($query) => $query->where('status', MissionStatus::PendingPayment->value))
            ->with('mission')
            ->get();

        $this->info("Found {$payments->count()} unpaid mission selection(s) requiring a reminder.");

        $sent = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                // Claim atomique : reste idempotent en cas de chevauchement d'exécutions. Une seule
                // valeur (à la seconde) sert au claim ET à sa libération. toBase() : sans toucher
                // updated_at, ancre de la dernière reprise du checkout (une relance n'en est pas une).
                $claimedAt = now()->startOfSecond();
                $claimed = MissionPayment::query()
                    ->whereKey($payment->id)
                    ->where('status', MissionPaymentStatus::Pending->value)
                    ->whereNull('reminder_sent_at')
                    ->toBase()
                    ->update(['reminder_sent_at' => $claimedAt]);

                if ($claimed === 0) {
                    continue;
                }

                if (! $this->notifyProducer($payment)) {
                    // Producteur injoignable (notification ET email en échec) : on libère la relance.
                    MissionPayment::query()
                        ->whereKey($payment->id)
                        ->where('reminder_sent_at', $claimedAt)
                        ->toBase()
                        ->update(['reminder_sent_at' => null]);

                    Log::warning('Mission selection payment reminder released: Producer could not be notified', [
                        'mission_payment_id' => $payment->id,
                    ]);
                    $this->warn("Reminder released for mission payment #{$payment->id}: Producer not notified, will retry.");
                    $failed++;

                    continue;
                }

                $sent++;
                $this->info("Reminder sent for mission payment #{$payment->id}");
            } catch (\Throwable $e) {
                Log::warning('Mission selection payment reminder failed', [
                    'mission_payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for mission payment #{$payment->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Retourne false si AUCUN des deux canaux (in-app, email) n'a abouti.
     */
    private function notifyProducer(MissionPayment $payment): bool
    {
        /** @var Mission $mission */
        $mission = $payment->mission;
        $deadline = Booking::formatForBusiness($this->missionPaymentService->selectionExpiresAt($payment), 'd/m \à H\hi');

        /** @var User|null $user */
        $user = User::where('userable_type', Producer::class)
            ->where('userable_id', $payment->producer_id)
            ->first();

        if ($user === null) {
            return false;
        }

        $notified = false;

        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'mission_payment_reminder',
                'data' => [
                    'message' => "Votre sélection pour « {$mission->titre} » n'est pas encore payée. Sans paiement avant le {$deadline}, elle sera annulée.",
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->uuid}/candidatures",
                ],
            ]);
            $notified = true;
        } catch (\Throwable $e) {
            Log::warning('Mission selection payment reminder notification failed', [
                'mission_payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $email = trim((string) $user->email);

            if ($email !== '') {
                Mail::to($email)->queue(new MissionPaymentReminderMail($mission, $payment, $deadline));
                $notified = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Mission selection payment reminder email queue failed', [
                'mission_payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notified;
    }
}
