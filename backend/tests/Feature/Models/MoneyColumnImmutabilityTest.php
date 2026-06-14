<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Exceptions\MoneyColumnImmutableException;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde d'immuabilité des colonnes de montant après création (hardening ugc-3-5, Item 2).
 * Les montants sont posés une fois à la création (services à payload explicite) puis figés ;
 * tout update Eloquent d'une colonne de montant doit throw.
 */
class MoneyColumnImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_money_columns_are_immutable_after_creation(): void
    {
        $booking = Booking::factory()->create();
        $original = (int) $booking->montant_total_producteur;

        try {
            $booking->update(['montant_total_producteur' => 999]);
            $this->fail('Un update de montant aurait dû lever MoneyColumnImmutableException.');
        } catch (MoneyColumnImmutableException) {
            // attendu
        }

        $this->assertSame($original, (int) $booking->fresh()->montant_total_producteur);
    }

    public function test_mission_payment_money_columns_are_immutable_after_creation(): void
    {
        $mission = Mission::factory()->create();
        $payment = $this->makePayment($mission->producer_id, $mission->id);

        $this->expectException(MoneyColumnImmutableException::class);
        $payment->update(['montant_total_producteur' => 999]);
    }

    public function test_mission_payment_candidature_money_columns_are_immutable_after_creation(): void
    {
        $producer = Producer::factory()->create();
        $face = Face::factory()->create();
        $mission = Mission::factory()->create(['producer_id' => $producer->id]);
        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
        $payment = $this->makePayment($producer->id, $mission->id);

        $entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Pending,
        ]);

        $this->expectException(MoneyColumnImmutableException::class);
        $entry->update(['montant_face_recoit' => 999]);
    }

    private function makePayment(int $producerId, int $missionId): MissionPayment
    {
        return MissionPayment::create([
            'mission_id' => $missionId,
            'producer_id' => $producerId,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Pending,
        ]);
    }
}
