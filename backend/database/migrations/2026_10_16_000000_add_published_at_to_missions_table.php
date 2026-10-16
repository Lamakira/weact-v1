<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `published_at` marks the FIRST publication of a mission: it drives the
     * « new mission » notifications (a reopened mission never re-notifies).
     * Missions already published at some point are backfilled so they can never
     * trigger a notification retroactively.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('status');
        });

        DB::table('missions')
            ->whereIn('status', ['published', 'closed', 'pending_attendance_validation', 'completed'])
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
