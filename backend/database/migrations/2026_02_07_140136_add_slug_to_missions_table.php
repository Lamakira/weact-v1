<?php

use App\Models\Mission;
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
        // Step 1: Add nullable slug column with index
        Schema::table('missions', function (Blueprint $table) {
            $table->string('slug', 200)->nullable()->after('titre');
            $table->index('slug');
        });

        // Step 2: Backfill existing missions with slugs from their title
        Mission::query()->whereNull('slug')->chunkById(100, function ($missions) {
            foreach ($missions as $mission) {
                $mission->slug = Mission::generateUniqueSlug($mission->titre, $mission->id);
                $mission->saveQuietly(); // Skip model events to avoid infinite loop
            }
        });

        // Step 3: Make NOT NULL + unique after backfill
        Schema::table('missions', function (Blueprint $table) {
            $table->string('slug', 200)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};
