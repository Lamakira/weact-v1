<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_contracts', function (Blueprint $table) {
            // Un contrat par candidature ; la mission supprimée ne doit pas emporter la preuve.
            $table->foreignId('candidature_id')->nullable()->unique()->after('booking_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('image_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('candidature_id');
        });
    }
};
