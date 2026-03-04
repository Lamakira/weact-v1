<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make booking_id nullable to support withdrawal events (not linked to a booking).
     */
    public function up(): void
    {
        Schema::table('financial_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('financial_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
        });
    }
};
