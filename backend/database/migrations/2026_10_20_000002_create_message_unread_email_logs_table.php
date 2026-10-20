<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mémorise le dernier e-mail « message non lu » envoyé par fil et par destinataire.
     * Une seule ligne par (destinataire, fil) : l'unicité sert de verrou atomique contre
     * deux exécutions simultanées de la commande planifiée.
     */
    public function up(): void
    {
        Schema::create('message_unread_email_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // 'conversation' (mission) ou 'booking' ; thread_id = id interne du fil
            $table->string('thread_type', 20);
            $table->unsignedBigInteger('thread_id');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'thread_type', 'thread_id'], 'message_unread_email_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_unread_email_logs');
    }
};
