<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('accepted_at')->nullable()->after('status');
            $table->index(['status', 'accepted_at'], 'bookings_status_accepted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_status_accepted_at_index');
            $table->dropColumn('accepted_at');
        });
    }
};
