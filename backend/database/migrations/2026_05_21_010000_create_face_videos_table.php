<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_videos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('face_id')->constrained('faces')->onDelete('cascade');
            $table->string('type', 20); // 'acting' | 'ugc' — plain string, no DB enum (NFR1 consistency)
            $table->string('filename', 255);
            $table->string('thumbnail', 255)->nullable();
            $table->unsignedInteger('position'); // No default — set explicitly by FaceVideoService
            $table->timestamps();

            $table->unique(['face_id', 'type', 'position'], 'unique_face_video_type_position');
            $table->index(['face_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_videos');
    }
};
