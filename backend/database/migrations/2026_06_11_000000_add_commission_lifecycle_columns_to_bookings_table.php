<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('commission_paid_at')->nullable()->after('commission_ugc');
            $table->timestamp('commission_refund_requested_at')->nullable()->after('commission_paid_at');
            $table->timestamp('commission_refunded_at')->nullable()->after('commission_refund_requested_at');
            $table->string('commission_refund_reason', 64)->nullable()->after('commission_refunded_at');
        });

        // Backfill : tout booking UGC déjà encaissé (FinancialEvent payment_confirmed)
        // reçoit l'horodatage réel du settlement. Idempotent (commission_paid_at IS NULL).
        DB::statement(<<<'SQL'
            UPDATE bookings b
            INNER JOIN financial_events fe
                ON fe.booking_id = b.id
                AND fe.type = 'payment_confirmed'
            SET b.commission_paid_at = fe.created_at
            WHERE BINARY b.type_contenu = 'UGC'
              AND b.commission_paid_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'commission_paid_at',
                'commission_refund_requested_at',
                'commission_refunded_at',
                'commission_refund_reason',
            ]);
        });
    }
};
