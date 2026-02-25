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
            $table->string('sexe', 10)->nullable()->after('username');
            $table->date('date_naissance')->nullable()->after('sexe');
            $table->string('nationalite', 100)->nullable()->after('date_naissance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn(['sexe', 'date_naissance', 'nationalite']);
        });
    }
};
