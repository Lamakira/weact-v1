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
     * Convert single-value categorie/niche ENUM columns to
     * multi-value categories/niches JSON array columns.
     */
    public function up(): void
    {
        // Step 1: Add new JSON columns
        Schema::table('faces', function (Blueprint $table) {
            $table->json('categories')->nullable()->after('poids');
            $table->json('niches')->nullable()->after('categories');
        });

        // Step 2: Migrate existing data
        // "acteur" → ["acteur"], NULL → []
        DB::statement('UPDATE faces SET categories = JSON_ARRAY(categorie) WHERE categorie IS NOT NULL');
        DB::statement("UPDATE faces SET categories = JSON_ARRAY() WHERE categorie IS NULL");
        DB::statement('UPDATE faces SET niches = JSON_ARRAY(niche) WHERE niche IS NOT NULL');
        DB::statement("UPDATE faces SET niches = JSON_ARRAY() WHERE niche IS NULL");

        // Step 3: Drop old ENUM columns
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['categorie', 'niche']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add ENUM columns
        Schema::table('faces', function (Blueprint $table) {
            $table->string('categorie')->nullable()->after('poids');
            $table->string('niche')->nullable()->after('categorie');
        });

        // Step 2: Migrate data back (take first element of array)
        DB::statement("UPDATE faces SET categorie = JSON_UNQUOTE(JSON_EXTRACT(categories, '$[0]')) WHERE JSON_LENGTH(categories) > 0");
        DB::statement("UPDATE faces SET niche = JSON_UNQUOTE(JSON_EXTRACT(niches, '$[0]')) WHERE JSON_LENGTH(niches) > 0");

        // Step 3: Drop JSON columns
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['categories', 'niches']);
        });
    }
};
