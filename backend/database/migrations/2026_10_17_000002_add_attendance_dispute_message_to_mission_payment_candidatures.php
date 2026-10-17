<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explication de la Face lorsqu'elle conteste une absence. Nullable, sans backfill :
     * null = contestation antérieure à l'ajout du message.
     */
    public function up(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table) {
            $table->text('attendance_dispute_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table) {
            $table->dropColumn('attendance_dispute_message');
        });
    }
};
