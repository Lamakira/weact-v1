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
        Schema::table('faces', function (Blueprint $table) {
            $table->string('presentation_video', 255)->nullable()->after('profile_photo_thumbnail');
            $table->string('presentation_video_thumbnail', 255)->nullable()->after('presentation_video');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['presentation_video', 'presentation_video_thumbnail']);
        });
    }
};
