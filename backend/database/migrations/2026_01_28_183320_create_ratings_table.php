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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')
                ->constrained('candidatures')
                ->cascadeOnDelete();
            $table->foreignId('rater_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->morphs('rated'); // Creates rated_id and rated_type
            $table->unsignedTinyInteger('score'); // 1-5, validate in model
            $table->text('comment')->nullable();
            $table->timestamps();

            // Unique constraint: one rating per rater per target per candidature
            $table->unique(['candidature_id', 'rater_id', 'rated_type', 'rated_id'], 'ratings_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
