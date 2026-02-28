<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('fedapay_ref')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('status', 30);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_events');
    }
};
