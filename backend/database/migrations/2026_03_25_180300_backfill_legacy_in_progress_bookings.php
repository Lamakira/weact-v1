<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->where('status', 'in_progress')
            ->update(['status' => 'paid']);
    }

    public function down(): void
    {
        // No reliable reverse mapping once legacy rows have been normalized to paid.
    }
};
