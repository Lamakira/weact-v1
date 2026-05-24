<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\FaceSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FailStalePendingFaceSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:fail-stale-pending';

    protected $description = 'Auto-fail Face subscription pending_payment rows older than the configured TTL (default 48h), unblocking the one-pending-row guard for legitimate re-attempts.';

    public function handle(): int
    {
        $maxHours = (int) config('face_subscription_tiers.stale_pending_max_hours', 48);
        $cutoff = now()->subHours($maxHours);

        $stale = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::PendingPayment)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        $this->info("Found {$stale->count()} subscription(s) to auto-fail.");

        $failed = 0;
        $skipped = 0;
        $errored = 0;

        foreach ($stale as $subscription) {
            try {
                $changed = DB::transaction(function () use ($subscription): bool {
                    /** @var FaceSubscription $locked */
                    $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

                    if ($locked->status !== FaceSubscriptionStatus::PendingPayment) {
                        return false;
                    }

                    $locked->update([
                        'status' => FaceSubscriptionStatus::Failed,
                        'metadata' => array_merge(
                            is_array($locked->metadata) ? $locked->metadata : [],
                            [
                                'stale_pending_at' => now()->toIso8601String(),
                                'stale_pending_reason' => 'auto_failed_by_cron',
                            ],
                        ),
                    ]);

                    return true;
                });

                if ($changed) {
                    Log::info('Face subscription auto-failed by stale-pending command', [
                        'face_subscription_id' => $subscription->id,
                        'face_id' => $subscription->face_id,
                        'plan' => $subscription->plan->value,
                        'created_at' => $subscription->created_at?->toIso8601String(),
                        'stale_hours_threshold' => $maxHours,
                    ]);
                    $ageHours = (int) $subscription->created_at->diffInHours(now());
                    $this->info("Auto-failed face subscription #{$subscription->id} (face #{$subscription->face_id}, plan: {$subscription->plan->value}, age_hours: {$ageHours})");
                    $failed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::error('Face subscription stale-pending auto-fail errored', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                    // Defensive: model casts `plan` non-null, but the catch
                    // path covers degenerate rows that slipped past FP-2.5's
                    // invariant. `'unknown'` is the operator-grep signal.
                    /** @phpstan-ignore nullCoalesce.expr */
                    'plan' => $subscription->plan?->value ?? 'unknown',
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Failed to auto-fail face subscription #{$subscription->id}: {$e->getMessage()}");
                $errored++;
            }
        }

        $this->info("Done. Failed: {$failed}, Skipped: {$skipped}, Errored: {$errored}.");

        return self::SUCCESS;
    }
}
