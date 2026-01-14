<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('annee');
            $table->timestamps();

            $table->index('annee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
