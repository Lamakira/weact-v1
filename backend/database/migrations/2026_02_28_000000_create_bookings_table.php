<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('face_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->unsignedInteger('duree_heures');
            $table->string('type_contenu', 100);
            $table->text('message')->nullable();
            $table->unsignedInteger('tarif_base');
            $table->unsignedInteger('montant_total_producteur');
            $table->unsignedInteger('montant_face_recoit');
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['producer_id', 'status']);
            $table->index(['face_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
