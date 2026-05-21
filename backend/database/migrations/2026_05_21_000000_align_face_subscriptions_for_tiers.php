<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FEATURE-FP-2.1 supersedes the FP-1 single-plan model.
     *
     * FP-1 (`plan = 'annual_premium'`) was never deployed to production, so the
     * face_subscriptions table is empty in every real environment — no data
     * migration is required (epic Product Decision #4). This migration removes
     * any leftover FP-1 row from developer-local databases: once the
     * FaceSubscriptionPlan enum drops `annual_premium`, a surviving FP-1 row
     * would crash enum hydration on read.
     *
     * The `plan` column stays a plain string (no DB enum/CHECK) so adding a
     * future tier requires zero schema change (FEAT-FP2-NFR1).
     */
    public function up(): void
    {
        // Hardcoded literal (not FaceSubscriptionPlan::values()): a migration
        // must stay frozen and not depend on app code that later evolves.
        DB::table('face_subscriptions')
            ->whereNotIn('plan', ['starter', 'pro', 'elite'])
            ->delete(); // FK ON DELETE CASCADE clears matching face_subscription_audits rows
    }

    public function down(): void
    {
        // Irreversible by design: the purged FP-1 rows were disposable dev/test
        // data (FP-1 never reached production). Nothing to restore.
    }
};
