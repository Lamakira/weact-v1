<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceSubscriptionTier;
use App\Enums\FaceVideoType;
use App\Models\Face;
use App\Models\FaceSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditFacePremiumReadinessCommand extends Command
{
    private const UNKNOWN_TIER_LABEL = 'Unknown';

    protected $signature = 'faces:audit-premium-readiness {--detailed}';

    protected $description = 'Read-only pre-launch audit (FP-2 tier model): per-tier active counts, over-quota Free Faces (photos + presentation/acting/UGC videos), subscription data-hygiene anomalies, and effective tier distribution across the whole Face population.';

    public function handle(): int
    {
        $detailed = (bool) $this->option('detailed');
        // Round 3 M4: format once and reuse the same string for every binding so MySQL's `>` comparison
        // is byte-identical between the outer where() and the inner whereRaw() (Carbon's __toString
        // may emit microseconds while the model's date formatter does not, drifting by µs at the boundary).
        $asOf = now()->format('Y-m-d H:i:s');

        // ---------- Section A — Active subscriptions by tier ----------
        // Round 3 M1: use DB::table to bypass the FaceSubscription Eloquent enum cast on the `plan` column.
        // While today's Eloquent pluck() happens to keep raw keys, any internal refactor (or a switch to
        // get()->keyBy()) would throw ValueError on legacy 'annual_premium' rows — the exact data the audit
        // is supposed to surface. The --detailed enumeration below uses the same defense for the same reason.
        $perTier = DB::table('face_subscriptions')
            ->where('status', FaceSubscriptionStatus::Active->value)
            ->where('expires_at', '>', $asOf)
            ->selectRaw('plan, COUNT(*) AS count')
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->all();

        $knownPlans = [
            FaceSubscriptionPlan::Starter->value,
            FaceSubscriptionPlan::Pro->value,
            FaceSubscriptionPlan::Elite->value,
        ];
        $starterCount = (int) ($perTier[FaceSubscriptionPlan::Starter->value] ?? 0);
        $proCount = (int) ($perTier[FaceSubscriptionPlan::Pro->value] ?? 0);
        $eliteCount = (int) ($perTier[FaceSubscriptionPlan::Elite->value] ?? 0);
        $unknownPlanCounts = array_diff_key($perTier, array_flip($knownPlans));
        $unknownCount = (int) array_sum($unknownPlanCounts);
        $totalActive = $starterCount + $proCount + $eliteCount + $unknownCount;

        $premiumFacesCount = FaceSubscription::query()
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', $asOf)
            ->distinct('face_id')
            ->count('face_id');

        $this->line('=== Face Premium readiness audit (FP-2 tier model) ===');
        $this->line('Active subscriptions by tier:');
        $this->line("  Starter: {$starterCount}");
        $this->line("  Pro:     {$proCount}");
        $this->line("  Élite:   {$eliteCount}");
        if ($unknownCount > 0) {
            $unknownPlans = array_map(static fn ($p): string => (string) $p, array_keys($unknownPlanCounts));
            $unknownList = implode(', ', $unknownPlans);
            $this->line("  Unknown: {$unknownCount} (plans: {$unknownList}) — investigate; legacy FP-1 or unwired tier");
        }
        $this->line("  Total:   {$totalActive}");
        $this->line("Distinct Faces with active paid subscription: {$premiumFacesCount}");

        if ($detailed && $unknownCount > 0) {
            // Query via DB::table to bypass the FaceSubscription Eloquent enum cast,
            // which would throw ValueError on legacy/unknown plan strings.
            DB::table('face_subscriptions')
                ->where('status', FaceSubscriptionStatus::Active->value)
                ->where('expires_at', '>', $asOf)
                ->whereNotIn('plan', $knownPlans)
                ->select('id', 'face_id', 'plan', 'expires_at')
                ->orderBy('face_id')
                ->orderBy('id')
                ->chunk(100, function ($subs): void {
                    foreach ($subs as $sub) {
                        $this->line("  - subscription#{$sub->id} face#{$sub->face_id} plan={$sub->plan} expires_at={$sub->expires_at}");
                    }
                });
        }
        $this->line('');

        // ---------- Section B — Free Faces with over-quota album photos ----------
        $freeFacesWithOverQuotaPhotosQuery = Face::query()
            ->whereDoesntHave('subscriptions', fn ($query) => $query
                ->where('status', FaceSubscriptionStatus::Active)
                ->where('expires_at', '>', $asOf))
            ->has('photos', '>', 1);

        $freeFacesWithOverQuotaPhotosCount = (clone $freeFacesWithOverQuotaPhotosQuery)->count();

        $this->line("Free Faces with > 1 album photo (positions 2+ will be hidden publicly at launch): {$freeFacesWithOverQuotaPhotosCount}");

        if ($detailed && $freeFacesWithOverQuotaPhotosCount > 0) {
            $freeFacesWithOverQuotaPhotosQuery
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

        // ---------- Section C — Free Faces with stored videos (3 surfaces) ----------
        $freeFilter = fn ($query) => $query
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', $asOf);

        $freeFacesWithPresentationVideoQuery = Face::query()
            ->whereDoesntHave('subscriptions', $freeFilter)
            ->whereNotNull('presentation_video')
            ->where('presentation_video', '!=', '');

        $freeFacesWithActingVideoQuery = Face::query()
            ->whereDoesntHave('subscriptions', $freeFilter)
            ->whereHas('videos', fn ($query) => $query->where('type', FaceVideoType::Acting->value));

        $freeFacesWithUgcVideoQuery = Face::query()
            ->whereDoesntHave('subscriptions', $freeFilter)
            ->whereHas('videos', fn ($query) => $query->where('type', FaceVideoType::Ugc->value));

        $presentationCount = (clone $freeFacesWithPresentationVideoQuery)->count();
        $actingCount = (clone $freeFacesWithActingVideoQuery)->count();
        $ugcCount = (clone $freeFacesWithUgcVideoQuery)->count();

        $this->line("Free Faces with a presentation video (hidden publicly at launch): {$presentationCount}");
        $this->line("Free Faces with an acting video    (hidden publicly at launch): {$actingCount}");
        $this->line("Free Faces with a UGC video        (hidden publicly at launch): {$ugcCount}");
        $this->line('Note: the 3 lines overlap — a single Free Face holding all 3 surfaces is counted in all 3 rows.');

        if ($detailed && $presentationCount > 0) {
            $freeFacesWithPresentationVideoQuery
                ->select('id', 'username', 'prenom')
                ->orderBy('id')
                ->chunk(100, function ($faces): void {
                    foreach ($faces as $face) {
                        $this->line("  - face#{$face->id} username={$face->username} prenom={$face->prenom} has_presentation_video=1");
                    }
                });
        }

        if ($detailed && $actingCount > 0) {
            $freeFacesWithActingVideoQuery
                ->select('id', 'username', 'prenom')
                ->withCount(['videos as acting_videos_count' => fn ($query) => $query->where('type', FaceVideoType::Acting->value)])
                ->orderBy('id')
                ->chunk(100, function ($faces): void {
                    foreach ($faces as $face) {
                        $this->line("  - face#{$face->id} username={$face->username} prenom={$face->prenom} acting_videos={$face->getAttribute('acting_videos_count')}");
                    }
                });
        }

        if ($detailed && $ugcCount > 0) {
            $freeFacesWithUgcVideoQuery
                ->select('id', 'username', 'prenom')
                ->withCount(['videos as ugc_videos_count' => fn ($query) => $query->where('type', FaceVideoType::Ugc->value)])
                ->orderBy('id')
                ->chunk(100, function ($faces): void {
                    foreach ($faces as $face) {
                        $this->line("  - face#{$face->id} username={$face->username} prenom={$face->prenom} ugc_videos={$face->getAttribute('ugc_videos_count')}");
                    }
                });
        }
        $this->line('');

        // ---------- Section D — Data hygiene anomalies (preserved verbatim) ----------
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

        // ---------- Section E — Effective tier distribution across all Faces ----------
        // D1: pick the canonical Active row per Face (mirrors Face::activeSubscription ofMany)
        // so a Face holding 2 concurrent Active rows of different plans contributes to
        // exactly one bucket (the one with the highest expires_at, ties broken by highest id).
        $canonicalActiveSub = DB::table('face_subscriptions as fs1')
            ->select('fs1.face_id', 'fs1.plan')
            ->where('fs1.status', FaceSubscriptionStatus::Active->value)
            ->where('fs1.expires_at', '>', $asOf)
            ->whereRaw('fs1.id = (
                SELECT fs2.id FROM face_subscriptions fs2
                WHERE fs2.face_id = fs1.face_id
                  AND fs2.status = ?
                  AND fs2.expires_at > ?
                ORDER BY fs2.expires_at DESC, fs2.id DESC
                LIMIT 1
            )', [FaceSubscriptionStatus::Active->value, $asOf]);

        $tierDistribution = DB::table('faces')
            ->leftJoinSub($canonicalActiveSub, 'active_sub', 'faces.id', '=', 'active_sub.face_id')
            ->selectRaw('
                CASE
                  WHEN active_sub.plan = ? THEN ?
                  WHEN active_sub.plan = ? THEN ?
                  WHEN active_sub.plan = ? THEN ?
                  WHEN active_sub.plan IS NOT NULL THEN ?
                  ELSE ?
                END AS effective_tier,
                COUNT(*) AS faces_count
            ', [
                FaceSubscriptionPlan::Starter->value, FaceSubscriptionTier::Starter->value,
                FaceSubscriptionPlan::Pro->value,     FaceSubscriptionTier::Pro->value,
                FaceSubscriptionPlan::Elite->value,   FaceSubscriptionTier::Elite->value,
                self::UNKNOWN_TIER_LABEL,
                FaceSubscriptionTier::Free->value,
            ])
            ->groupBy('effective_tier')
            ->pluck('faces_count', 'effective_tier')
            ->all();

        $freeFaces = (int) ($tierDistribution[FaceSubscriptionTier::Free->value] ?? 0);
        $starterFaces = (int) ($tierDistribution[FaceSubscriptionTier::Starter->value] ?? 0);
        $proFaces = (int) ($tierDistribution[FaceSubscriptionTier::Pro->value] ?? 0);
        $eliteFaces = (int) ($tierDistribution[FaceSubscriptionTier::Elite->value] ?? 0);
        $unknownFaces = (int) ($tierDistribution[self::UNKNOWN_TIER_LABEL] ?? 0);
        $totalFaces = $freeFaces + $starterFaces + $proFaces + $eliteFaces + $unknownFaces;

        $counts = [
            'Free' => $freeFaces,
            'Starter' => $starterFaces,
            'Pro' => $proFaces,
            'Élite' => $eliteFaces,
        ];
        if ($unknownFaces > 0) {
            $counts['Unknown'] = $unknownFaces;
        }

        $percents = $this->bucketPercents($counts, $totalFaces);

        $this->line('Effective tier distribution across all Faces:');
        $this->line("  Free:    {$freeFaces} ({$percents['Free']}%)");
        $this->line("  Starter: {$starterFaces} ({$percents['Starter']}%)");
        $this->line("  Pro:     {$proFaces} ({$percents['Pro']}%)");
        $this->line("  Élite:   {$eliteFaces} ({$percents['Élite']}%)");
        if ($unknownFaces > 0) {
            $this->line("  Unknown: {$unknownFaces} ({$percents['Unknown']}%) — see Section A enumeration above");
        }
        $this->line("  Total:   {$totalFaces} Faces");
        $this->line('');

        $this->line('Audit complete.');

        return self::SUCCESS;
    }

    /**
     * Render percentages that sum to exactly 100% on a non-empty total by flooring all buckets
     * and crediting the rounding residual to the largest **canonical-tier** bucket. The Unknown
     * bucket is deliberately excluded from the residual-target search (Round 3 R3-H2) so the
     * operator-readable count is not biased by 1-2 points when Unknown happens to dominate.
     *
     * @param  array<string, int>  $counts  must contain Free / Starter / Pro / Élite keys; Unknown is optional.
     * @return array<string, int>
     */
    private function bucketPercents(array $counts, int $total): array
    {
        if ($total <= 0) {
            return array_map(static fn (): int => 0, $counts);
        }

        $percents = [];
        foreach ($counts as $key => $n) {
            $percents[$key] = (int) floor(($n / $total) * 100);
        }

        $residual = 100 - array_sum($percents);
        if ($residual === 0) {
            return $percents;
        }

        // Round 3 R3-H2: pick the residual target from canonical tiers only — never Unknown —
        // so operators reading the audit are not led to over-report the legacy/unwired-plan magnitude.
        $canonicalTiers = ['Free', 'Starter', 'Pro', 'Élite'];
        $largestKey = $canonicalTiers[0];
        $largestValue = $counts[$largestKey] ?? 0;
        foreach ($canonicalTiers as $key) {
            $value = $counts[$key] ?? 0;
            if ($value > $largestValue) {
                $largestValue = $value;
                $largestKey = $key;
            }
        }
        $percents[$largestKey] += $residual;

        return $percents;
    }
}
