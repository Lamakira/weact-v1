<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('fedapay_transaction_id')->nullable()->after('cancellation_reason');
            $table->string('payment_mode', 30)->nullable()->after('fedapay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['fedapay_transaction_id', 'payment_mode']);
        });
    }
};
