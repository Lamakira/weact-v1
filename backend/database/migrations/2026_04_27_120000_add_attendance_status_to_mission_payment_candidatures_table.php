<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
            $table->enum('attendance_status', ['pending', 'present', 'absent', 'disputed'])
                ->default('pending')
                ->after('escrow_status');
        });
    }

    public function down(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
            $table->dropColumn('attendance_status');
        });
    }
};
