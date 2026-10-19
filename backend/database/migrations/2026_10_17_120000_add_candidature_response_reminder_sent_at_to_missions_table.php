<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotence de la relance « répondez aux candidatures » envoyée au Producer (lot B6).
     * Nullable, sans backfill : null = relance pas encore envoyée.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->timestamp('candidature_response_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('candidature_response_reminder_sent_at');
        });
    }
};
