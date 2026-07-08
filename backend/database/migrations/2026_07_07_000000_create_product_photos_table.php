<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Colonnes morph manuelles (owner = Booking | Mission) : l'index de
            // morphs() serait strictement redondant avec l'unique composite
            // ci-dessous (même préfixe owner_type, owner_id) — calque deliverables.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('kind', 20)->default('product'); // la spec B ajoutera `reception` (Shipment)
            $table->unsignedTinyInteger('position');        // 1-2 (max 2 photos par owner)
            // Disque posé à la création (row autoportante, réutilisée telle quelle
            // par la spec B) : `public` pour Mission (vitrine candidates), disque
            // UGC privé pour Booking (URLs signées réservées aux deux parties).
            $table->string('disk', 20);
            $table->string('filename');
            $table->string('grid')->nullable();   // variantes remplies par GenerateImageVariants
            $table->string('large')->nullable();
            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_id', 'kind', 'position'],
                'product_photos_owner_kind_position_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_photos');
    }
};
