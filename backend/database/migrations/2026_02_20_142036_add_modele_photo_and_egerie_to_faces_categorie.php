<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE faces MODIFY COLUMN categorie ENUM('acteur', 'influenceur', 'createur', 'mannequin', 'figurant', 'modele_photo', 'egerie') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, clear any rows using the new values
        DB::table('faces')->whereIn('categorie', ['modele_photo', 'egerie'])->update(['categorie' => null]);

        DB::statement("ALTER TABLE faces MODIFY COLUMN categorie ENUM('acteur', 'influenceur', 'createur', 'mannequin', 'figurant') NULL");
    }
};
