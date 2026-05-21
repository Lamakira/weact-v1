<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use Illuminate\Console\Command;

class AuditFacePremiumReadinessCommand extends Command
{
    protected $signature = 'faces:audit-premium-readiness {--detailed}';

    protected $description = 'Read-only pre-launch audit: counts of Faces affected by Face Premium masking at rollout, plus subscription data-hygiene anomalies.';

    public function handle(): int
    {
        $detailed = (bool) $this->option('detailed');
        $asOf = now();

        // Section A — Active premium overview
        $activeSubscriptionsCount = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', $asOf)
            ->count();

        $premiumFacesCount = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', $asOf)
            ->distinct('face_id')
            ->count('face_id');

        $this->line('=== Face Premium readiness audit ===');
        $this->line("Active premium subscriptions: {$activeSubscriptionsCount}");
        $this->line("Distinct Faces with active premium: {$premiumFacesCount}");
        $this->line('');

        // Section B — Free Faces with locked album photos at launch
        $freeFacesWithLockedPhotosQuery = Face::query()
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('status', FaceSubscriptionStatus::Active)
                ->where('expires_at', '>', $asOf))
            ->has('photos', '>', 2);

        $freeFacesWithLockedPhotosCount = (clone $freeFacesWithLockedPhotosQuery)->count();

        $this->line("Free Faces with > 2 album photos (positions 3-4 will be locked at launch): {$freeFacesWithLockedPhotosCount}");

        if ($detailed && $freeFacesWithLockedPhotosCount > 0) {
            $freeFacesWithLockedPhotosQuery
                ->select('id', 'username', 'prenom')
                ->withCount('photos')
                ->orderBy('photos_count', 'desc')
                ->orderBy('id')
                ->chunk(100, function ($faces): void {
                    foreach ($faces as $face) {
                        $this->line("  - face#{$face->id} username={$face->username} prenom={$face->prenom} photos_count={$face->photos_count}");
                    }
                });
        }
        $this->line('');

        // Section C — Free Faces with locked acting video at launch
        $freeFacesWithActingVideoQuery = Face::query()
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('status', FaceSubscriptionStatus::Active)
                ->where('expires_at', '>', $asOf))
            ->whereNotNull('acting_video');

        $freeFacesWithActingVideoCount = (clone $freeFacesWithActingVideoQuery)->count();

        $this->line("Free Faces with non-null acting_video (will be hidden publicly at launch): {$freeFacesWithActingVideoCount}");

        if ($detailed && $freeFacesWithActingVideoCount > 0) {
            $freeFacesWithActingVideoQuery
                ->select('id', 'username', 'prenom', 'acting_video')
                ->orderBy('id')
                ->chunk(100, function ($faces): void {
                    foreach ($faces as $face) {
                        $this->line("  - face#{$face->id} username={$face->username} prenom={$face->prenom} acting_video={$face->acting_video}");
                    }
                });
        }
        $this->line('');

        // Section D — Data hygiene anomalies (read-only surface)
        $activeWithNullExpiresCount = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->whereNull('expires_at')
            ->count();

        $activeWithPastExpiresCount = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf)
            ->count();

        $this->line('--- Data hygiene anomalies (will not block launch, route to a hardening story if non-zero) ---');
        $this->line("Active subscriptions with NULL expires_at: {$activeWithNullExpiresCount}");
        $this->line("Active subscriptions with past expires_at (stale, awaiting expiry cron): {$activeWithPastExpiresCount}");
        $this->line('');

        $this->line('Audit complete.');

        return self::SUCCESS;
    }
}
