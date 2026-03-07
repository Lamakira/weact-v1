<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill accepted_at for existing accepted bookings using updated_at as proxy.
        // These bookings were accepted before the accepted_at column was introduced.
        DB::table('bookings')
            ->where('status', 'accepted')
            ->whereNull('accepted_at')
            ->update(['accepted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // Non-reversible: we cannot know which accepted_at values were backfilled.
    }
};
