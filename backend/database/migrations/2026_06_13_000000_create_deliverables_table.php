<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Colonnes morph manuelles (owner = Booking | Candidature) : l'index
            // de morphs() serait strictement redondant avec l'unique composite
            // ci-dessous (même préfixe owner_type, owner_id) — calque shipments.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('kind', 20);                 // DeliverableKind : unboxing | avis (D-4.1.a, pas d'extra)
            $table->string('validation_status', 30);    // DeliverableValidationStatus : 4.1 n'écrit que in_review
            $table->timestamp('chrono_started_at');     // snapshot de recu_le à l'upload (D-4.1.b)
            $table->timestamp('deadline_at');           // snapshot de la dérivée serveur (recu_le + 7 j)
            $table->string('video_path');               // chemin relatif sur le disque privé `local`
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duree_seconds')->nullable();
            $table->text('review_note')->nullable();    // posé en 4.3 (validation Producteur)
            $table->timestamp('validated_at')->nullable(); // posé en 4.3
            $table->timestamps();

            // UN livrable par (deal, kind) — backstop DB de l'idempotence service (AC3).
            $table->unique(['owner_type', 'owner_id', 'kind'], 'deliverables_owner_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
