<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_change_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('date_change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidature_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['date_change_request_id', 'candidature_id'], 'date_change_responses_request_candidature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_change_responses');
    }
};
