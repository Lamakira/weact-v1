<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes for common query patterns:
     * - (mission_id, status) for producer viewing mission candidatures filtered by status
     * - (face_id, status) for face viewing their candidatures filtered by status
     */
    public function up(): void
    {
        Schema::table('candidatures', function (Blueprint $table) {
            $table->index(['mission_id', 'status'], 'candidatures_mission_status_index');
            $table->index(['face_id', 'status'], 'candidatures_face_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidatures', function (Blueprint $table) {
            $table->dropIndex('candidatures_mission_status_index');
            $table->dropIndex('candidatures_face_status_index');
        });
    }
};
