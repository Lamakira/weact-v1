<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ugc_suspensions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Face suspendue (faces.id). cascadeOnDelete : une Face supprimée
            // emporte ses suspensions (donnée historique sans valeur orpheline).
            $table->foreignId('face_id')->constrained('faces')->cascadeOnDelete();
            // Shipment (deal) à l'origine de la suspension. nullOnDelete : on
            // garde la trace de la suspension même si le shipment disparaît.
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('reason', 40);                       // UgcSuspensionReason
            $table->string('appeal_status', 20)->default('none'); // UgcSuspensionAppealStatus (5.1 n'écrit que 'none')
            $table->timestamp('suspended_at');
            $table->timestamp('reactivated_at')->nullable();    // posé par 5.3 (dégel), null = suspension active
            $table->timestamps();

            // Lookup isUgcSuspended : suspension active d'une Face (reactivated_at IS NULL).
            $table->index(['face_id', 'reactivated_at']);

            // PAS d'unique sur face_id seul : MySQL n'a pas d'index partiel, donc
            // un unique bloquerait toute re-suspension après réactivation (les
            // anciennes lignes gardent un reactivated_at non-null). L'unicité
            // « une suspension active par Face » est garantie applicativement
            // (UgcSuspensionService : lockForUpdate + garde whereNull).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ugc_suspensions');
    }
};
