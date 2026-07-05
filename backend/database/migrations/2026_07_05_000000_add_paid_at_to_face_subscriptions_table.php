<?php

declare(strict_types=1);

use App\Support\FaceSubscriptionPaidAtBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Garde d'idempotence : le DDL MySQL est auto-committé, donc si le
        // backfill ci-dessous crashe (lock-wait contre un webhook live,
        // coupure, SIGTERM de déploiement), la migration n'est PAS enregistrée
        // alors que la colonne existe déjà — sans cette garde, chaque retry
        // mourrait en « Duplicate column name 'paid_at' » et bloquerait tout
        // le pipeline de migrations.
        if (! Schema::hasColumn('face_subscriptions', 'paid_at')) {
            Schema::table('face_subscriptions', function (Blueprint $table): void {
                // dateTime (pas timestamp) : homogène avec les colonnes métier
                // sœurs starts_at/expires_at/cancelled_at — pas de conversion
                // par time_zone de session ni de plafond 2038.
                $table->dateTime('paid_at')->nullable()->index()->after('paid_amount');
            });
        }

        // Backfill rétroactif (D-1) : logique partagée avec la commande
        // re-exécutable `face-subscriptions:backfill-paid-at` — voir
        // App\Support\FaceSubscriptionPaidAtBackfill pour les choix (PHP +
        // Carbon, non-datable, idempotence). Relancer la commande une fois
        // après le redémarrage des workers : une activation commise par
        // l'ANCIEN code dans la fenêtre migrate→queue:restart écrit
        // paid_amount sans paid_at et échapperait à ce one-shot.
        //
        // Lignes payées non-datables : comptées dans le cumul total des
        // revenus, absentes des agrégats par période.
        $result = FaceSubscriptionPaidAtBackfill::run();

        Log::info('face_subscriptions paid_at backfill terminé', [
            'backfilled_rows' => $result['backfilled'],
            'non_datable_rows' => $result['non_datable'],
        ]);
    }

    public function down(): void
    {
        Schema::table('face_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });
    }
};
