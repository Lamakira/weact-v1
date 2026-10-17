<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lot B3 — confirmation de la Face sous 48 h : relance unique (claim atomique).
     *
     * Backfill : les candidatures cash `accepted` existantes n'ont pas d'`accepted_at`
     * (seul l'UGC le posait). On l'ancre sur la date de paiement de la sélection
     * (`mission_payments.paid_at`, à défaut `updated_at`) pour que l'échéance de
     * confirmation leur soit applicable.
     */
    public function up(): void
    {
        Schema::table('candidatures', function (Blueprint $table): void {
            $table->timestamp('confirmation_reminder_sent_at')->nullable()->after('accepted_at');
        });

        $rows = DB::table('candidatures as c')
            ->join('mission_payment_candidatures as e', 'e.candidature_id', '=', 'c.id')
            ->join('mission_payments as p', 'p.id', '=', 'e.mission_payment_id')
            ->where('c.status', 'accepted')
            ->whereNull('c.accepted_at')
            ->whereNotNull('e.mission_payment_id')
            ->select('c.id', DB::raw('COALESCE(p.paid_at, p.updated_at) as anchor'))
            ->get();

        foreach ($rows as $row) {
            if ($row->anchor === null) {
                continue;
            }

            DB::table('candidatures')
                ->where('id', $row->id)
                ->whereNull('accepted_at')
                ->update(['accepted_at' => $row->anchor]);
        }
    }

    public function down(): void
    {
        Schema::table('candidatures', function (Blueprint $table): void {
            $table->dropColumn('confirmation_reminder_sent_at');
        });
    }
};
