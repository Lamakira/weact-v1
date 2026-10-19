<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horodatage de la relance « payez votre sélection avant 24 h » (claim atomique de la commande).
     * Nullable, sans backfill : les paiements déjà en attente ne sont relancés que s'ils sont
     * encore dans la fenêtre de relance.
     */
    public function up(): void
    {
        Schema::table('mission_payments', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mission_payments', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
