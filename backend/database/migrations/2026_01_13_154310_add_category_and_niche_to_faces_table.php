<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->enum('categorie', ['acteur', 'influenceur', 'createur', 'mannequin', 'figurant'])
                ->nullable()
                ->after('poids');
            $table->enum('niche', ['beaute', 'nourriture', 'decouverte', 'mode'])
                ->nullable()
                ->after('categorie');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['categorie', 'niche']);
        });
    }
};
