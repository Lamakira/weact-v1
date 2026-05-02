<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mission_payment_candidatures')
            ->whereNull('attendance_status')
            ->update(['attendance_status' => 'pending']);
    }

    public function down(): void
    {
        // Data backfill only; intentionally not reversible.
    }
};
