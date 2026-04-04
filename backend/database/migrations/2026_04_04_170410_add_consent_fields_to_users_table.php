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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('consent_given_at')->nullable()->after('email_verified_at');
            $table->string('consent_ip', 45)->nullable()->after('consent_given_at');
            $table->string('consent_version', 20)->nullable()->after('consent_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consent_given_at', 'consent_ip', 'consent_version']);
        });
    }
};
