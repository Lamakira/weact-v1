<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Décision PO 2026-07-06 : une activation manuelle enregistre 0 comme
     * montant (aucun encaissement on-platform). Backfill rétroactif des
     * lignes créées par l'ancien code (paid_amount NULL) — identifiées par
     * leur audit manual_activate, seul chemin qui crée ces lignes. Les
     * NULL restants (pending_payment/failed jamais payés) sont préservés.
     * Idempotent : le whereNull rend tout re-run no-op.
     */
    public function up(): void
    {
        $backfilled = DB::table('face_subscriptions')
            ->whereNull('paid_amount')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('face_subscription_audits')
                    ->whereColumn('face_subscription_audits.face_subscription_id', 'face_subscriptions.id')
                    ->where('face_subscription_audits.action', 'manual_activate');
            })
            ->update(['paid_amount' => 0]);

        Log::info('face_subscriptions manual-activation paid_amount backfill terminé', [
            'backfilled_rows' => $backfilled,
        ]);
    }

    public function down(): void
    {
        // Réversible sans ambiguïté : les webhooks n'écrivent jamais 0
        // (montants FedaPay strictement positifs), donc paid_amount = 0 +
        // audit manual_activate ⇔ ligne touchée par ce backfill ou créée
        // par le nouveau code — les deux reviennent à l'état legacy NULL.
        DB::table('face_subscriptions')
            ->where('paid_amount', 0)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('face_subscription_audits')
                    ->whereColumn('face_subscription_audits.face_subscription_id', 'face_subscriptions.id')
                    ->where('face_subscription_audits.action', 'manual_activate');
            })
            ->update(['paid_amount' => null]);
    }
};
