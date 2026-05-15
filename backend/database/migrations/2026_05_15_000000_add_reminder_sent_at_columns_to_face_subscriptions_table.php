<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_subscriptions', function (Blueprint $table): void {
            $table->dateTime('reminder_30d_sent_at')->nullable()->after('cancelled_at');
            $table->dateTime('reminder_7d_sent_at')->nullable()->after('reminder_30d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('face_subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['reminder_30d_sent_at', 'reminder_7d_sent_at']);
        });
    }
};
