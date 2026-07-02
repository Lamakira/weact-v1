<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Mail\FaceSubscriptionRenewalReminderMail;
use App\Models\FaceSubscription;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindFaceSubscriptionRenewalsCommand extends Command
{
    protected $signature = 'subscriptions:remind-face-renewals';

    protected $description = 'Send 30-day and 7-day renewal reminders to active annual Face premium subscribers, idempotently per window.';

    public function handle(): int
    {
        $now = now();
        $window30Start = $now->copy()->addDays(29);
        $window30End = $now->copy()->addDays(30);
        $window7Start = $now->copy()->addDays(6);
        $window7End = $now->copy()->addDays(7);

        $sent30 = 0;
        $sent7 = 0;
        $failed = 0;

        // 30-day window: expires_at in [now()+29d, now()+30d], reminder_30d_sent_at IS NULL
        $stale30 = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->whereBetween('expires_at', [$window30Start, $window30End])
            ->whereNull('reminder_30d_sent_at')
            ->orderBy('id')
            ->get();

        $this->info("Found {$stale30->count()} subscription(s) needing 30-day reminder.");
        foreach ($stale30 as $subscription) {
            try {
                $reminder = DB::transaction(function () use ($subscription, $window30Start, $window30End): ?array {
                    /** @var FaceSubscription $locked */
                    $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

                    if ($locked->status !== FaceSubscriptionStatus::Active
                        || $locked->expires_at === null
                        || ! $locked->expires_at->betweenIncluded($window30Start, $window30End)
                        || $locked->reminder_30d_sent_at !== null
                    ) {
                        return null;
                    }

                    $this->sendReminder($locked, daysRemaining: 30);

                    $locked->update(['reminder_30d_sent_at' => now()]);

                    return $this->reminderLogContext($locked, daysRemaining: 30);
                });

                if ($reminder !== null) {
                    $this->info("30-day reminder sent for face subscription #{$reminder['face_subscription_id']} (face #{$reminder['face_id']}, plan: {$reminder['plan']})");
                    Log::info('Face subscription 30-day reminder dispatched by scheduled command', $reminder);
                    $sent30++;
                }
            } catch (\Throwable $e) {
                Log::error('Face subscription 30d reminder failed', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                    'plan' => $this->planLogValue($subscription),
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Failed 30d for face subscription #{$subscription->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        // 7-day window: expires_at in [now()+6d, now()+7d], reminder_7d_sent_at IS NULL
        $stale7 = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->whereBetween('expires_at', [$window7Start, $window7End])
            ->whereNull('reminder_7d_sent_at')
            ->orderBy('id')
            ->get();

        $this->info("Found {$stale7->count()} subscription(s) needing 7-day reminder.");
        foreach ($stale7 as $subscription) {
            try {
                $reminder = DB::transaction(function () use ($subscription, $window7Start, $window7End): ?array {
                    /** @var FaceSubscription $locked */
                    $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

                    if ($locked->status !== FaceSubscriptionStatus::Active
                        || $locked->expires_at === null
                        || ! $locked->expires_at->betweenIncluded($window7Start, $window7End)
                        || $locked->reminder_7d_sent_at !== null
                    ) {
                        return null;
                    }

                    $this->sendReminder($locked, daysRemaining: 7);

                    $locked->update(['reminder_7d_sent_at' => now()]);

                    return $this->reminderLogContext($locked, daysRemaining: 7);
                });

                if ($reminder !== null) {
                    $this->info("7-day reminder sent for face subscription #{$reminder['face_subscription_id']} (face #{$reminder['face_id']}, plan: {$reminder['plan']})");
                    Log::info('Face subscription 7-day reminder dispatched by scheduled command', $reminder);
                    $sent7++;
                }
            } catch (\Throwable $e) {
                Log::error('Face subscription 7d reminder failed', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                    'plan' => $this->planLogValue($subscription),
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Failed 7d for face subscription #{$subscription->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Sent30d: {$sent30}, Sent7d: {$sent7}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    private function sendReminder(FaceSubscription $subscription, int $daysRemaining): void
    {
        /** @var \App\Models\Face|null $face */
        $face = $subscription->face()->with('user')->first();
        if (! $face) {
            return;
        }

        $faceUser = $face->user;
        if (! $faceUser) {
            return;
        }

        // In-app notification (always — primary channel, in-transaction so commit guarantees persistence)
        $expiresLabel = $subscription->expires_at?->locale('fr')->translatedFormat('d F Y') ?? '';
        $planLabel = $subscription->plan->label();
        $premiumMediaSummary = $subscription->plan->premiumMediaSummary();
        Notification::create([
            'user_id' => $faceUser->id,
            'type' => $daysRemaining === 30 ? 'face_subscription_renewal_reminder_30d' : 'face_subscription_renewal_reminder_7d',
            'data' => [
                'message' => "Rappel : votre abonnement {$planLabel} expire dans {$daysRemaining} jours (le {$expiresLabel}). Renouvelez pour garder {$premiumMediaSummary} publiques.",
                'face_subscription_id' => $subscription->id,
                'days_remaining' => $daysRemaining,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
                'url' => '/face/profile',
            ],
        ]);

        // Email (best-effort — wrapped so a mail failure does not break the lifecycle update)
        $faceEmail = trim((string) $faceUser->email);
        if ($faceEmail === '') {
            return;
        }

        try {
            Mail::to($faceEmail)->queue(new FaceSubscriptionRenewalReminderMail(
                face: $face,
                subscription: $subscription,
                daysRemaining: $daysRemaining,
            ));
        } catch (\Throwable $e) {
            Log::warning('FaceSubscriptionRenewalReminderMail queue failed', [
                'face_subscription_id' => $subscription->id,
                'face_id' => $face->id,
                'plan' => $this->planLogValue($subscription),
                'days_remaining' => $daysRemaining,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{face_subscription_id: int, face_id: int, plan: string, days_remaining: int, expires_at: string|null}
     */
    private function reminderLogContext(FaceSubscription $subscription, int $daysRemaining): array
    {
        return [
            'face_subscription_id' => $subscription->id,
            'face_id' => $subscription->face_id,
            'plan' => $this->planLogValue($subscription),
            'days_remaining' => $daysRemaining,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ];
    }

    private function planLogValue(FaceSubscription $subscription): string
    {
        $rawPlan = $subscription->getRawOriginal('plan');
        if ($rawPlan instanceof \BackedEnum) {
            return (string) $rawPlan->value;
        }

        if (is_scalar($rawPlan)) {
            $plan = trim((string) $rawPlan);

            return $plan !== '' ? $plan : 'unknown';
        }

        return 'unknown';
    }
}
