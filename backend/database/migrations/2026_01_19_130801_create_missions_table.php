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
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained('producers')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->text('description');
            $table->date('date_tournage');
            $table->text('profil_recherche');
            $table->unsignedInteger('budget'); // XOF in integers
            $table->date('date_limite_candidature');
            $table->unsignedSmallInteger('nombre_faces_voulu')->default(1);
            $table->string('type_mission', 50); // enum: publicite, film, court_metrage, clip_musical, autre
            $table->string('genre_voulu', 20); // enum: homme, femme, tous
            $table->string('lieu', 150);
            $table->string('duree', 100); // e.g., "2 jours", "4 heures"
            $table->string('status', 20)->default('draft'); // enum: draft, published, closed, completed
            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index('date_tournage');
            $table->index('date_limite_candidature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
