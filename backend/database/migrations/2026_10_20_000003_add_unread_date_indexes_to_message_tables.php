<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La commande messages:remind-unread cherche les messages non lus dans une fenêtre de dates
     * (WHERE read_at IS NULL AND created_at BETWEEN ...) : sans cet index elle parcourrait
     * toute la table à chaque passage (toutes les 15 minutes).
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['read_at', 'created_at'], 'messages_unread_window_idx');
        });

        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->index(['read_at', 'created_at'], 'booking_messages_unread_window_idx');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_unread_window_idx');
        });

        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->dropIndex('booking_messages_unread_window_idx');
        });
    }
};
