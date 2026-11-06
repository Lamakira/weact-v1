<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_change_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requested_by_role', 20); // producer | face
            $table->date('old_date');
            $table->date('old_end_date')->nullable();
            $table->date('new_date');
            $table->date('new_end_date')->nullable();
            $table->string('reason', 30);
            $table->string('status', 20);
            $table->timestamp('respond_by');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'respond_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_change_requests');
    }
};
