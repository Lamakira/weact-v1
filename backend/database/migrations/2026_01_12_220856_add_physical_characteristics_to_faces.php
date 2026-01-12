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
            $table->unsignedSmallInteger('taille')->nullable()->after('pays');
            $table->unsignedSmallInteger('poids')->nullable()->after('taille');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['taille', 'poids']);
        });
    }
};
