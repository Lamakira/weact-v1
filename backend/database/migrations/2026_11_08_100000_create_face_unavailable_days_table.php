<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_unavailable_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('face_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->timestamps();

            $table->unique(['face_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_unavailable_days');
    }
};
