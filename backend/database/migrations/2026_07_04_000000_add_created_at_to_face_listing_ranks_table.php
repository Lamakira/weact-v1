<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Generation birth timestamp, written by faces:rebuild-listing-ranks at
 * insert time. Powers the staleness watchdog
 * (faces:check-listing-ranks-freshness): if MAX(created_at) is older than
 * the alert threshold, the nightly rebuild has been silently failing and
 * the public rotation is frozen. useCurrent() only backfills pre-existing
 * dev rows — the command always sets the value explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_listing_ranks', function (Blueprint $table): void {
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('face_listing_ranks', function (Blueprint $table): void {
            $table->dropColumn('created_at');
        });
    }
};
