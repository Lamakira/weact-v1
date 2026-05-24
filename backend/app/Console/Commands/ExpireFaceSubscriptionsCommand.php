<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Events\FaceSubscriptionExpired;
use App\Models\FaceSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireFaceSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:expire-faces';

    /**
     * The console command description.
     */
    protected $description = 'Expire annual Face premium subscriptions whose expires_at is in the past, without deleting any stored media.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stale = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        $this->info("Found {$stale->count()} subscription(s) to expire.");

        $expired = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($stale as $subscription) {
            try {
                $changed = DB::transaction(function () use ($subscription): bool {
                    /** @var FaceSubscription $locked */
                    $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

                    if ($locked->status !== FaceSubscriptionStatus::Active
                        || $locked->expires_at === null
                        || $locked->expires_at->isFuture()
                    ) {
                        return false;
                    }

                    $locked->update(['status' => FaceSubscriptionStatus::Expired]);

                    return true;
                });

                if ($changed) {
                    Log::info('Face subscription expired by scheduled command', [
                        'face_subscription_id' => $subscription->id,
                        'face_id' => $subscription->face_id,
                        'plan' => $subscription->plan->value,
                        'previous_expires_at' => $subscription->expires_at?->toIso8601String(),
                    ]);
                    $this->info("Expired face subscription #{$subscription->id} (face #{$subscription->face_id}, plan: {$subscription->plan->value})");
                    FaceSubscriptionExpired::dispatch($subscription->fresh());
                    $expired++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::error('Face subscription expiration failed', [
                    'face_subscription_id' => $subscription->id,
                    'face_id' => $subscription->face_id,
                    'plan' => $subscription->plan->value,
                    'error_class' => $e::class,
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Failed to expire face subscription #{$subscription->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Expired: {$expired}, Skipped: {$skipped}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
