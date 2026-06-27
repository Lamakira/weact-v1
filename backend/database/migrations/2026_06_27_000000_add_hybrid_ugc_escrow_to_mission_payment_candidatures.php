<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Règlement hybride par-Face (ugc-8-4, D-8.4.a/b).
     *
     * Le paiement hybride d'une mission UGC est par-Candidature (chaque acceptation
     * = une transaction FedaPay distincte). `mission_payments.mission_id` et
     * `.fedapay_transaction_id` sont UNIQUE ⇒ impossible d'avoir N MissionPayment par
     * mission, ni un parent portant N transactions. On crée donc des
     * MissionPaymentCandidature PARENTLESS (`mission_payment_id` NULL) portant chacune
     * leur propre `fedapay_transaction_id`. Les entries cash existantes gardent leur
     * `mission_payment_id` (relâchement non destructif, pas de backfill).
     */
    public function up(): void
    {
        Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
            // Relâchement non destructif : les entries cash gardent leur parent ;
            // une entry hybride parentless porte mission_payment_id = NULL.
            $table->unsignedBigInteger('mission_payment_id')->nullable()->change();

            // Le paiement FedaPay par-Face de l'hybride (string, calque
            // bookings/mission_payments.fedapay_transaction_id ; lookup webhook D-8.4.f).
            $table->string('fedapay_transaction_id')->nullable()->unique()->after('mission_payment_id');
        });
    }

    public function down(): void
    {
        // Les entries hybrides PARENTLESS (mission_payment_id NULL) créées par cette story
        // empêcheraient le re-NOT-NULL ci-dessous. On les purge d'abord : rollback de cette
        // migration = abandon du règlement hybride par-Candidature, donc ces entries (qui
        // n'existent QUE grâce à cette feature) n'ont plus de raison d'être. Les entries cash
        // gardent toutes leur mission_payment_id (non touchées).
        DB::table('mission_payment_candidatures')->whereNull('mission_payment_id')->delete();

        Schema::table('mission_payment_candidatures', function (Blueprint $table): void {
            $table->dropUnique(['fedapay_transaction_id']);
            $table->dropColumn('fedapay_transaction_id');
            // Re-NOT NULL : sûr maintenant que les entries parentless ont été purgées ci-dessus.
            $table->unsignedBigInteger('mission_payment_id')->nullable(false)->change();
        });
    }
};
