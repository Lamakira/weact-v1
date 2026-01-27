<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candidatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->text('message_motivation')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            // Unique constraint: A Face can only apply once per mission
            $table->unique(['face_id', 'mission_id']);

            // Index for common queries (filtering by status)
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
