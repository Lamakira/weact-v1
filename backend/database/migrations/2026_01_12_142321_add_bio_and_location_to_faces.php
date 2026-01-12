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
            $table->text('bio')->nullable()->after('acting_video_thumbnail');
            $table->string('ville', 100)->nullable()->after('bio');
            $table->string('quartier', 100)->nullable()->after('ville');
            $table->string('pays', 100)->nullable()->default('Bénin')->after('quartier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['bio', 'ville', 'quartier', 'pays']);
        });
    }
};
