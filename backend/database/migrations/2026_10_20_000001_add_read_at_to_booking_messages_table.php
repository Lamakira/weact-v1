<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('content');

            // Compteur de fils non lus : filtre par booking, messages non lus, hors expéditeur.
            $table->index(['booking_id', 'read_at', 'sender_id'], 'booking_messages_unread_idx');
        });

        $this->backfill();
    }

    /**
     * Rétroactif : les messages existants sont considérés comme lus, sinon le
     * déploiement allumerait un faux badge « non lu » sur chaque ancien booking.
     */
    public function backfill(): void
    {
        DB::table('booking_messages')->whereNull('read_at')->update(['read_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('booking_messages', function (Blueprint $table): void {
            $table->dropIndex('booking_messages_unread_idx');
            $table->dropColumn('read_at');
        });
    }
};
