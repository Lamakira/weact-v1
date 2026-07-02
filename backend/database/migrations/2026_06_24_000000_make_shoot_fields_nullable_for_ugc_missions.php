<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une mission UGC (dotation) n'a ni date, ni durée, ni lieu de tournage → relâcher
     * ces champs (NOT NULL, strict superset du standard) en nullable pour l'UGC.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->date('date_tournage')->nullable()->change();
            $table->string('lieu', 150)->nullable()->change();
            $table->string('duree', 100)->nullable()->change();
        });

        // Nettoie les valeurs vestigiales sur les missions UGC déjà créées.
        DB::table('missions')
            ->where('type_mission', 'ugc')
            ->update([
                'date_tournage' => null,
                'lieu' => null,
                'duree' => null,
            ]);
    }

    public function down(): void
    {
        // Backfill avant de re-serrer NOT NULL, sinon le tightening échoue.
        DB::table('missions')->whereNull('date_tournage')->update(['date_tournage' => DB::raw('created_at')]);
        DB::table('missions')->whereNull('lieu')->update(['lieu' => '—']);
        DB::table('missions')->whereNull('duree')->update(['duree' => '—']);

        Schema::table('missions', function (Blueprint $table) {
            $table->date('date_tournage')->nullable(false)->change();
            $table->string('lieu', 150)->nullable(false)->change();
            $table->string('duree', 100)->nullable(false)->change();
        });
    }
};
