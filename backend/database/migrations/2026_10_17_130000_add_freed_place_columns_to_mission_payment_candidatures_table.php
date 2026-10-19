<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot B4 : place libérée (Face sélectionnée non confirmée) et remplacement.
     *  - freed_at : la place est libérée, l'argent reste en séquestre en attendant une
     *    remplaçante ou le remboursement au début du jour de tournage ;
     *  - replacement_of_entry_id : sur l'entry de la remplaçante, l'entry dont elle reprend
     *    la place (et l'argent).
     *  - escrow_status gagne 'transferred' : l'argent d'une place libérée est passé à
     *    l'entry de la remplaçante (état terminal).
     * Additif, nullable, sans backfill.
     */
    public function up(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table) {
            $table->enum('escrow_status', ['pending', 'locked', 'released', 'refunded', 'transferred'])
                ->default('pending')
                ->change();
            $table->timestamp('freed_at')->nullable()->index();
            $table->foreignId('replacement_of_entry_id')
                ->nullable()
                ->constrained('mission_payment_candidatures')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_of_entry_id');
            $table->dropIndex(['freed_at']);
            $table->dropColumn('freed_at');
        });

        // Retour arrière : une entry transférée n'existe pas avant ce lot ; elle n'a plus d'argent
        // (la remplaçante le porte) et est rangée en « refunded » pour respecter l'ancien enum.
        DB::table('mission_payment_candidatures')
            ->where('escrow_status', 'transferred')
            ->update(['escrow_status' => 'refunded']);

        Schema::table('mission_payment_candidatures', function (Blueprint $table) {
            $table->enum('escrow_status', ['pending', 'locked', 'released', 'refunded'])
                ->default('pending')
                ->change();
        });
    }
};
