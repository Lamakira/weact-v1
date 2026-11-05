<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Position instantanée partagée dans un message (jamais de suivi en direct).
     * `content` reste renseigné (texte neutre) : aperçus, push et e-mails n'ont rien à changer.
     */
    public function up(): void
    {
        foreach (['messages', 'booking_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('location_lat', 9, 6)->nullable()->after('content');
                $table->decimal('location_lng', 9, 6)->nullable()->after('location_lat');
                $table->unsignedInteger('location_accuracy_m')->nullable()->after('location_lng');
            });
        }
    }

    public function down(): void
    {
        foreach (['messages', 'booking_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['location_lat', 'location_lng', 'location_accuracy_m']);
            });
        }
    }
};
