<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consentement aux alertes WhatsApp. Nullable, sans backfill : null = non inscrit.
     * `whatsapp_opt_in_number` mémorise le numéro normalisé consenti : changer de numéro de profil
     * rend l'inscription caduque.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_opt_in_at')->nullable();
            $table->string('whatsapp_opt_in_number', 20)->nullable()->index();
            $table->timestamp('whatsapp_invite_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_opt_in_number']);
            $table->dropColumn(['whatsapp_opt_in_at', 'whatsapp_opt_in_number', 'whatsapp_invite_dismissed_at']);
        });
    }
};
