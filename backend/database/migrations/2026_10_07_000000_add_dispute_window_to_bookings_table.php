<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fenêtre de contestation de 72 h (absence Face / annulation Producteur tardive)
     * et relance des bookings payés sans suite. Additive, nullable, sans backfill :
     * `settlement_due_at` null = déjà réglé (lignes historiques).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('settlement_due_at')->nullable()->index();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_message')->nullable();
            $table->timestamp('dispute_resolved_at')->nullable();
            $table->string('dispute_outcome', 20)->nullable();
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('dispute_admin_notes')->nullable();
            $table->timestamp('completion_reminder_sent_at')->nullable();
            $table->timestamp('face_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropIndex(['settlement_due_at']);
            $table->dropColumn([
                'settlement_due_at',
                'disputed_at',
                'dispute_message',
                'dispute_resolved_at',
                'dispute_outcome',
                'dispute_resolved_by',
                'dispute_admin_notes',
                'completion_reminder_sent_at',
                'face_confirmed_at',
            ]);
        });
    }
};
