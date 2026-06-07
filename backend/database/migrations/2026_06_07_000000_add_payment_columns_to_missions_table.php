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
            $table->unsignedBigInteger('fedapay_transaction_id')->nullable()->after('commission_ugc');
            $table->uuid('payment_initiation_key')->nullable()->after('fedapay_transaction_id');
            $table->timestamp('commission_paid_at')->nullable()->after('payment_initiation_key');
            $table->unique('fedapay_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table): void {
            $table->dropUnique('missions_fedapay_transaction_id_unique');
            $table->dropColumn(['fedapay_transaction_id', 'payment_initiation_key', 'commission_paid_at']);
        });
    }
};
