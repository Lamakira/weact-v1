<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotence du rappel « tournage demain » envoyé aux deux parties d'un booking cash payé (lot D6).
     * Nullable, sans backfill : null = rappel pas encore envoyé.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('shooting_reminder_sent_at')->nullable()->after('response_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('shooting_reminder_sent_at');
        });
    }
};
