<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->timestamp('commission_refund_requested_at')->nullable()->after('commission_paid_at');
            $table->timestamp('commission_refunded_at')->nullable()->after('commission_refund_requested_at');
            $table->string('commission_refund_reason', 64)->nullable()->after('commission_refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->dropColumn([
                'commission_refund_requested_at',
                'commission_refunded_at',
                'commission_refund_reason',
            ]);
        });
    }
};
