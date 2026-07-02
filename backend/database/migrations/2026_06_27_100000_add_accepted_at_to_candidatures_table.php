<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ancrage temporel de la reconfirmation post-acceptation (ugc-9-1, D-9.1.a).
     *
     * Posé au flip `→ Accepted` aux DEUX sites d'acceptation (produit-seul
     * Producer\CandidatureController::accept + hybride
     * MissionPaymentService::markUgcMissionCandidaturePaid). Le sweep
     * `ugc:expire-unreconfirmed-candidatures` dénoue une candidature acceptée mais
     * jamais reconfirmée après `ugc.reconfirm_window_hours` (48 h).
     *
     * Pas de backfill : les candidatures `accepted` existantes n'ont pas d'ancrage —
     * acceptable, le module hybride n'est pas déployé en prod et le sweep ignore
     * `accepted_at IS NULL` via `whereNotNull` (défense supplémentaire).
     */
    public function up(): void
    {
        Schema::table('candidatures', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('candidatures', function (Blueprint $table): void {
            $table->dropColumn('accepted_at');
        });
    }
};
