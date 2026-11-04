<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_image_terms', function (Blueprint $table) {
            $table->id();
            // Conditions d'image figées à la publication, signées une seule fois par le Producteur.
            $table->foreignId('mission_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_version', 20);
            $table->string('finalite', 200);
            $table->json('supports');
            $table->string('territoire', 30);
            $table->unsignedTinyInteger('duree_annees');
            $table->string('producer_signed_name', 150);
            $table->timestamp('producer_signed_at');
            $table->string('producer_signed_email_masked');
            $table->string('producer_user_agent', 255)->nullable(); // jamais exposé
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_image_terms');
    }
};
