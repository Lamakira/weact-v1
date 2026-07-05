<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_subscriptions', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->index()->after('paid_amount');
        });

        // Backfill rétroactif (D-1) : date l'encaissement depuis metadata.confirmed_at
        // pour toutes les lignes déjà payées (paid_amount non-null).
        //
        // En PHP + Carbon plutôt qu'en STR_TO_DATE SQL, pour deux raisons :
        // 1. Une valeur hors-plage (ex. « 2026-06-31T… ») ferait échouer l'UPDATE
        //    en mode SQL strict APRÈS l'ALTER TABLE (DDL déjà commité) — la
        //    migration resterait bloquée en « duplicate column » au retry. Ici,
        //    la ligne tombe simplement dans le compteur non-datable.
        // 2. Carbon::parse honore un éventuel offset non-UTC (converti via
        //    ->utc()) au lieu de le tronquer silencieusement.
        // Idempotent : ne touche que les lignes où paid_at est encore NULL.
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

        // Lignes payées non-datables : comptées dans le cumul total des
        // revenus, absentes des agrégats par période.
        Log::info('face_subscriptions paid_at backfill terminé', [
            'backfilled_rows' => $backfilled,
            'non_datable_rows' => $nonDatable,
        ]);
    }

    public function down(): void
    {
        Schema::table('face_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('paid_at');
        });
    }
};
