<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_payment_candidatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidature_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('face_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('montant_face_recoit');
            $table->enum('escrow_status', ['pending', 'locked', 'released', 'refunded'])->default('pending');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_payment_candidatures');
    }
};
