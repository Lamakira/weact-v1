<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotence de la relance « répondez à la demande » envoyée à la Face (lot B10).
     * Nullable, sans backfill : null = relance pas encore envoyée.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('response_reminder_sent_at')->nullable()->after('payment_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('response_reminder_sent_at');
        });
    }
};
