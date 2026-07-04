<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_listing_ranks', function (Blueprint $table) {
            $table->id();
            // Ranking generation. The current generation is MAX(generation):
            // a rebuild inserts generation N+1 inside one transaction, so the
            // switch is atomic by construction (no settings/pointer table).
            $table->unsignedInteger('generation');
            // cascadeOnDelete: a deleted Face takes its rank rows with it —
            // a rank row is worthless without its Face.
            $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
            $table->unsignedInteger('rank');

            // One rank per Face per generation.
            $table->unique(['generation', 'face_id']);
            // Indexed sorted read for the public listing ORDER BY.
            $table->index(['generation', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_listing_ranks');
    }
};
