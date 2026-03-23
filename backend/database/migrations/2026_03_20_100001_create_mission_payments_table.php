<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mission_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('nombre_faces_retenues');
            $table->unsignedInteger('budget_par_face');
            $table->unsignedInteger('montant_sous_total');
            $table->unsignedInteger('commission_producteur');
            $table->unsignedInteger('montant_total_producteur');
            $table->unsignedInteger('commission_faces_total');
            $table->unsignedInteger('montant_total_faces');
            $table->string('fedapay_transaction_id')->nullable()->unique();
            $table->string('fedapay_ref')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_payments');
    }
};
