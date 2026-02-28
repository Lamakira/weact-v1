<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fedapay_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('fedapay_event_id')->unique();
            $table->string('event_name', 50);
            $table->json('payload');
            $table->string('status', 20)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fedapay_webhook_events');
    }
};
