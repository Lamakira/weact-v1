<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Colonnes morph manuelles (owner = Booking | Candidature) : l'index
            // de morphs() serait strictement redondant avec l'unique composite
            // ci-dessous (même préfixe owner_type, owner_id).
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('transporteur', 100);
            $table->string('numero_suivi', 100);
            $table->string('note_envoi', 500)->nullable();
            $table->string('tunnel_status', 30);
            $table->timestamp('shipped_at');
            $table->timestamp('recu_le')->nullable(); // posé par 3.3 (« Produit reçu »), jamais ici
            $table->string('destinataire_nom', 255);
            $table->string('destinataire_ville', 100)->nullable();
            $table->string('destinataire_pays', 100)->nullable();
            $table->timestamps();

            // UN shipment par deal (D-3.1.b) — backstop DB de l'idempotence service.
            $table->unique(['owner_type', 'owner_id'], 'shipments_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
