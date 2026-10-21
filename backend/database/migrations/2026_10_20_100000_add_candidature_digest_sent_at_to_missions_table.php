<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horodatage du dernier e-mail groupé « nouvelles candidatures » envoyé au Producer (lot D5).
     * Une colonne sur la mission suffit : c'est un curseur (« depuis quand compter ») et un
     * limiteur (au plus un envoi par heure et par mission), sans historique à conserver.
     *
     * Rétroactif : les missions existantes démarrent à « maintenant », sinon le premier passage
     * enverrait un récapitulatif de toutes les candidatures anciennes encore en attente.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->timestamp('candidature_digest_sent_at')->nullable();
        });

        DB::table('missions')->update(['candidature_digest_sent_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('candidature_digest_sent_at');
        });
    }
};
