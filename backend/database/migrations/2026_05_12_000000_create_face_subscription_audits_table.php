<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_subscription_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('face_subscription_id')
                ->constrained('face_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('admins')
                ->nullOnDelete();
            $table->string('action', 30);
            $table->text('notes');
            $table->json('previous_state')->nullable();
            $table->json('new_state');
            $table->timestamp('created_at')->nullable();

            $table->index('face_subscription_id');
            $table->index('admin_id');
            $table->index('created_at');
            $table->index(['face_subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_subscription_audits');
    }
};
