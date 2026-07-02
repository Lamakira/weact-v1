<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
            $table->string('plan', 50);
            $table->string('status', 30);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedInteger('paid_amount')->nullable();
            $table->string('currency', 3)->default('XOF');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('face_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index(['face_id', 'status', 'expires_at']);
            $table->unique('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_subscriptions');
    }
};
