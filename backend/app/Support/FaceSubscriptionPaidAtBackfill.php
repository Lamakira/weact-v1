<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfill rétroactif de face_subscriptions.paid_at (décision D-1) : date
 * l'encaissement depuis metadata.confirmed_at pour toutes les lignes payées
 * (paid_amount non-null) encore non datées (paid_at null).
 *
 * Logique partagée entre la migration d'introduction de la colonne et la
 * commande `face-subscriptions:backfill-paid-at` (rattrapage re-exécutable :
 * une activation commise par l'ANCIEN code pendant la fenêtre de déploiement
 * migrate-avant-restart-des-workers écrit paid_amount + confirmed_at sans
 * paid_at — la migration one-shot ne la reverra jamais).
 *
 * Idempotent : ne touche que les lignes où paid_at est encore NULL.
 */
final class FaceSubscriptionPaidAtBackfill
{
    /**
     * En PHP + Carbon plutôt qu'en STR_TO_DATE SQL : un JSON illisible ou un
     * confirmed_at non-parsable ne fait pas échouer l'UPDATE — la ligne tombe
     * dans le compteur non-datable. (Limite assumée : Carbon::parse NORMALISE
     * une date hors-plage — « 2026-06-31 » devient le 1er juillet sans
     * exception — mais l'unique écrivain de confirmed_at émet
     * now()->toIso8601String(), toujours valide.) Carbon::parse honore aussi
     * un éventuel offset non-UTC (converti via ->utc()) au lieu de le
     * tronquer silencieusement.
     *
     * @return array{backfilled: int, non_datable: int}
     */
    public static function run(): array
    {
        $backfilled = 0;
        $nonDatable = 0;

        DB::table('face_subscriptions')
            ->select(['id', 'metadata'])
            ->whereNotNull('paid_amount')
            ->whereNull('paid_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$backfilled, &$nonDatable): void {
                foreach ($rows as $row) {
                    $meta = is_string($row->metadata) ? json_decode($row->metadata, true) : null;
                    $confirmedAt = is_array($meta) ? ($meta['confirmed_at'] ?? null) : null;

                    if (! is_string($confirmedAt) || trim($confirmedAt) === '') {
                        $nonDatable++;

                        continue;
                    }

                    try {
                        $paidAt = Carbon::parse($confirmedAt)->utc();
                    } catch (Throwable) {
                        $nonDatable++;

                        continue;
                    }

                    DB::table('face_subscriptions')
                        ->where('id', $row->id)
                        ->update(['paid_at' => $paidAt]);
                    $backfilled++;
                }
            });

        return ['backfilled' => $backfilled, 'non_datable' => $nonDatable];
    }
}
