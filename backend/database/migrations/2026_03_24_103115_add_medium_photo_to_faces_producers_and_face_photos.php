<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->string('profile_photo_medium')->nullable()->after('profile_photo_thumbnail');
        });

        Schema::table('producers', function (Blueprint $table) {
            $table->string('profile_photo_medium')->nullable()->after('profile_photo_thumbnail');
        });

        Schema::table('face_photos', function (Blueprint $table) {
            $table->string('medium')->nullable()->after('thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn('profile_photo_medium');
        });

        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn('profile_photo_medium');
        });

        Schema::table('face_photos', function (Blueprint $table) {
            $table->dropColumn('medium');
        });
    }
};
