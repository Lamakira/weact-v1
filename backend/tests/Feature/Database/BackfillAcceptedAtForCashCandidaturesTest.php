<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillAcceptedAtForCashCandidaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_cash_candidature_without_accepted_at_gets_the_payment_date(): void
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->closed()->create(['producer_id' => $producer->id]);
        $paidAt = now()->subHours(5)->startOfSecond();
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 2,
            'budget_par_face' => 100000,
            'montant_sous_total' => 200000,
            'commission_producteur' => 20000,
            'montant_total_producteur' => 220000,
            'commission_faces_total' => 20000,
            'montant_total_faces' => 180000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        $cash = Candidature::factory()->create(['mission_id' => $mission->id, 'status' => CandidatureStatus::Accepted]);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $cash->id,
            'face_id' => $cash->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
        ]);
        // Sans entry cash (ex. UGC produit-seul) : jamais touchée.
        $noEntry = Candidature::factory()->create(['mission_id' => $mission->id, 'status' => CandidatureStatus::Accepted]);
        // Déjà ancrée : conservée.
        $anchoredAt = now()->subDays(3)->startOfSecond();
        $anchored = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Accepted,
            'accepted_at' => $anchoredAt,
        ]);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $anchored->id,
            'face_id' => $anchored->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
        ]);

        // Rejoue la migration : retire la colonne ajoutée puis exécute up().
        Schema::table('candidatures', fn ($table) => $table->dropColumn('confirmation_reminder_sent_at'));
        $migration = require database_path('migrations/2026_10_17_110000_add_confirmation_reminder_sent_at_to_candidatures_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('candidatures', 'confirmation_reminder_sent_at'));
        $this->assertSame($paidAt->toDateTimeString(), $cash->fresh()->accepted_at?->toDateTimeString());
        $this->assertNull($noEntry->fresh()->accepted_at);
        $this->assertSame($anchoredAt->toDateTimeString(), $anchored->fresh()->accepted_at?->toDateTimeString());
    }
}
