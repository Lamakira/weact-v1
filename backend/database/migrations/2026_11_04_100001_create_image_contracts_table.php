<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Un contrat par booking ; le booking supprimé ne doit pas emporter la preuve.
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('template_version', 20);
            $table->string('finalite', 200);
            $table->json('supports');
            $table->string('territoire', 30);
            $table->unsignedTinyInteger('duree_annees');
            $table->json('snapshot'); // parties, projet, contrepartie : figés à la création
            $table->string('producer_signed_name', 150);
            $table->timestamp('producer_signed_at');
            $table->string('producer_signed_email_masked');
            $table->string('face_signed_name', 150)->nullable();
            $table->timestamp('face_signed_at')->nullable();
            $table->string('face_signed_email_masked')->nullable();
            $table->string('producer_user_agent', 255)->nullable(); // jamais exposé
            $table->string('face_user_agent', 255)->nullable(); // jamais exposé
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_relation', 20)->nullable();
            $table->string('pdf_path')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('copies_sent_at')->nullable(); // posé une fois les copies PDF envoyées
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_contracts');
    }
};
