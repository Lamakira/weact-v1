<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->string('profile_photo_grid')->nullable()->after('profile_photo_medium');
            $table->string('profile_photo_large')->nullable()->after('profile_photo_grid');
        });

        Schema::table('producers', function (Blueprint $table) {
            $table->string('profile_photo_grid')->nullable()->after('profile_photo_medium');
            $table->string('profile_photo_large')->nullable()->after('profile_photo_grid');
        });

        Schema::table('face_photos', function (Blueprint $table) {
            $table->string('grid')->nullable()->after('medium');
            $table->string('large')->nullable()->after('grid');
        });
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_grid', 'profile_photo_large']);
        });

        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_grid', 'profile_photo_large']);
        });

        Schema::table('face_photos', function (Blueprint $table) {
            $table->dropColumn(['grid', 'large']);
        });
    }
};
