<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le motif de refus d'un booking est un texte libre de 1000 caractères
     * (RefuseBookingRequest) : VARCHAR(255) faisait échouer un refus motivé long.
     * Élargissement sans perte ; les valeurs d'enum d'annulation tiennent toujours.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->change();
        });
    }

    /**
     * Retour arrière : tronque à 255 caractères avant de réduire la colonne,
     * sinon le ALTER échoue en mode strict dès qu'un motif long existe.
     */
    public function down(): void
    {
        DB::table('bookings')
            ->whereRaw('CHAR_LENGTH(cancellation_reason) > 255')
            ->update(['cancellation_reason' => DB::raw('LEFT(cancellation_reason, 255)')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancellation_reason', 255)->nullable()->change();
        });
    }
};
