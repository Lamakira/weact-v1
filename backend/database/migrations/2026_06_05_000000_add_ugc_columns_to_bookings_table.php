<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('type_compensation', 20)->nullable()->after('type_contenu');
            $table->string('nom_produit', 255)->nullable()->after('type_compensation');
            $table->unsignedInteger('valeur_produit')->nullable()->after('nom_produit');
            $table->unsignedSmallInteger('nombre_videos')->nullable()->after('valeur_produit');
            $table->unsignedInteger('montant_remuneration')->nullable()->after('nombre_videos');
            $table->unsignedInteger('commission_ugc')->nullable()->after('montant_remuneration');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'type_compensation', 'nom_produit', 'valeur_produit',
                'nombre_videos', 'montant_remuneration', 'commission_ugc',
            ]);
        });
    }
};
