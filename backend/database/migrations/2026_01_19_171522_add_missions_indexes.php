<?php

declare(strict_types=1);

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
        Schema::table('missions', function (Blueprint $table) {
            // Composite index for acceptingCandidatures scope: WHERE status = 'published' AND date_limite >= now()
            $table->index(['status', 'date_limite_candidature'], 'missions_accepting_candidatures_index');

            // Index for filtering by mission type (story 5-9)
            $table->index('type_mission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropIndex('missions_accepting_candidatures_index');
            $table->dropIndex(['type_mission']);
        });
    }
};
