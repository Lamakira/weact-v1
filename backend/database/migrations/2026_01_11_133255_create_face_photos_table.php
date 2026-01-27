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
        Schema::create('face_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('face_id')->constrained('faces')->onDelete('cascade');
            $table->string('filename', 255);
            $table->string('thumbnail', 255)->nullable();
            $table->unsignedInteger('position'); // No default - must be set explicitly by service
            $table->timestamps();

            // Unique constraint to prevent duplicate positions for the same face
            $table->unique(['face_id', 'position'], 'unique_face_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_photos');
    }
};
