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
            $table->string('acting_video', 255)->nullable()->after('presentation_video_thumbnail');
            $table->string('acting_video_thumbnail', 255)->nullable()->after('acting_video');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['acting_video', 'acting_video_thumbnail']);
        });
    }
};
