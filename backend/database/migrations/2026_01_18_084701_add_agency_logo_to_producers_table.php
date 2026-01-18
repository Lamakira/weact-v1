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
        Schema::table('producers', function (Blueprint $table) {
            $table->string('agency_logo')->nullable()->after('bio');
            $table->string('agency_logo_thumbnail')->nullable()->after('agency_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn(['agency_logo', 'agency_logo_thumbnail']);
        });
    }
};
