<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unique('fedapay_transaction_id');
        });

        Schema::table('escrow_transactions', function (Blueprint $table): void {
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique('bookings_fedapay_transaction_id_unique');
        });

        Schema::table('escrow_transactions', function (Blueprint $table): void {
            $table->dropUnique('escrow_transactions_booking_id_unique');
        });
    }
};
