<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            // 4.5 — ancre d'idempotence de l'escalade : nb de paliers déjà notifiés
            // pour le CHRONO ACTIF courant (0 = aucun). Ré-armé à 0 au passage
            // Unboxing validé → avis_pending (nouvelle chrono). D-4.5.a/e.
            $table->unsignedTinyInteger('last_notified_threshold')->default(0)->after('recu_le');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropColumn('last_notified_threshold');
        });
    }
};
