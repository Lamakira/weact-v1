<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Story 3.8.1: Replace annee (year) with date_debut and date_fin (start/end dates)
     */
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // Add new date columns
            $table->date('date_debut')->nullable()->after('description');
            $table->date('date_fin')->nullable()->after('date_debut');
        });

        // Migrate existing data: convert annee to date_debut and date_fin
        DB::table('experiences')->whereNotNull('annee')->update([
            'date_debut' => DB::raw("CONCAT(annee, '-01-01')"),
            'date_fin' => DB::raw("CONCAT(annee, '-12-31')"),
        ]);

        Schema::table('experiences', function (Blueprint $table) {
            // Make date_debut required (after migration)
            $table->date('date_debut')->nullable(false)->change();

            // Drop old annee column and its index
            $table->dropIndex(['annee']);
            $table->dropColumn('annee');

            // Add index on date_debut
            $table->index('date_debut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            // Add back annee column
            $table->unsignedSmallInteger('annee')->nullable()->after('description');
        });

        // Migrate data back: extract year from date_debut
        DB::table('experiences')->whereNotNull('date_debut')->update([
            'annee' => DB::raw('YEAR(date_debut)'),
        ]);

        Schema::table('experiences', function (Blueprint $table) {
            // Make annee required
            $table->unsignedSmallInteger('annee')->nullable(false)->change();

            // Drop date columns and their index
            $table->dropIndex(['date_debut']);
            $table->dropColumn(['date_debut', 'date_fin']);

            // Add back index on annee
            $table->index('annee');
        });
    }
};
