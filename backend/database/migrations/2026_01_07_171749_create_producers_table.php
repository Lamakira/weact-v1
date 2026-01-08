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
        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['agency', 'particulier']);
            $table->string('agency_name')->nullable(); // Only for type='agency'
            $table->string('first_name')->nullable();  // Only for type='particulier'
            $table->string('last_name')->nullable();   // Only for type='particulier'
            $table->timestamps();

            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('producers');
    }
};

