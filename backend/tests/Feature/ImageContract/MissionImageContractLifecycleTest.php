<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\ProductReceived;
use App\Events\UgcCommissionRefunded;
use App\Models\Candidature;
use App\Models\ImageContract;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Shipment;
use App\Services\MissionPaymentService;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

/**
 * Prise d'effet et annulation de l'autorisation d'image d'une candidature de mission.
 * Règle de sûreté : sans événement certain le contrat reste « signé » (non effectif), et une
 * prise d'effet acquise n'est jamais annulée.
 */
class MissionImageContractLifecycleTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpParties();
    }

    private function payments(): MissionPaymentService
    {
        return app(MissionPaymentService::class);
    }

    private function release(MissionPaymentCandidature $entry, Mission $mission): void
    {
        DB::transaction(fn () => $this->payments()->releaseToFace($entry, $mission));
    }

    private function refund(MissionPaymentCandidature $entry, Mission $mission): void
    {
        DB::transaction(fn () => $this->payments()->refundToProducer($entry, $mission));
    }

    private function receivedShipment(Candidature $owner): Shipment
    {
        return $owner->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-1',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDay(),
            'recu_le' => now(),
            'destinataire_nom' => 'Aïcha Bello',
        ]);
    }

    /**
     * Mission cash terminée côté paiement : candidature engagée, contrat signé, escrow Locked.
     *
     * @return array{0: Mission, 1: Candidature, 2: MissionPaymentCandidature, 3: ImageContract}
     */
    private function engagedCash(): array
    {
        [$mission, $candidature, $entry] = $this->cashMissionWithAcceptedFace();
        $candidature->update(['status' => CandidatureStatus::InProgress]);

        return [$mission, $candidature, $entry, $this->makeCandidatureContract($candidature)];
    }

    // ===================================================================
    // Prise d'effet
    // ===================================================================

    public function test_releasing_the_escrow_makes_the_contract_effective(): void
    {
        [$mission, , $entry, $contract] = $this->engagedCash();

        $this->release($entry, $mission);

        $this->assertNotNull($contract->fresh()->effective_at);
        $this->assertSame('effective', $contract->fresh()->status());
    }

    public function test_releasing_a_hybrid_ugc_escrow_makes_the_contract_effective(): void
    {
        [$mission, $candidature, $entry] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        $this->release($entry, $mission);

        $this->assertNotNull($contract->fresh()->effective_at);
    }

    public function test_the_release_leaves_another_candidature_contract_alone_and_does_not_resurrect_a_void_one(): void
    {
        [$mission, , $entry, $contract] = $this->engagedCash();
        $other = Candidature::factory()->create(['mission_id' => $mission->id, 'status' => CandidatureStatus::Confirmed]);
        $otherContract = $this->makeCandidatureContract($other);

        $contract->update(['voided_at' => now()]);
        $this->release($entry, $mission);

        $this->assertNull($contract->fresh()->effective_at);
        $this->assertNull($otherContract->fresh()->effective_at);
    }

    public function test_product_received_makes_a_product_only_candidature_contract_effective(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        ProductReceived::dispatch($this->receivedShipment($candidature));

        $this->assertNotNull($contract->fresh()->effective_at);
    }

    public function test_product_received_does_not_make_a_hybrid_or_a_cash_candidature_contract_effective(): void
    {
        [, $hybrid] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);
        $hybrid->update(['status' => CandidatureStatus::Confirmed]);
        $hybridContract = $this->makeCandidatureContract($hybrid);
        [, $cash] = $this->cashMissionWithAcceptedFace();
        $cashContract = $this->makeCandidatureContract($cash);

        ProductReceived::dispatch($this->receivedShipment($hybrid));
        ProductReceived::dispatch($this->receivedShipment($cash));

        $this->assertNull($hybridContract->fresh()->effective_at);
        $this->assertNull($cashContract->fresh()->effective_at);
    }

    public function test_a_hybrid_contract_stays_signed_until_the_escrow_is_released(): void
    {
        [$mission, $candidature, $entry] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        ProductReceived::dispatch($this->receivedShipment($candidature));
        $this->assertSame('signed', $contract->fresh()->status());

        $this->release($entry, $mission);
        $this->assertSame('effective', $contract->fresh()->status());
    }

    public function test_an_unsigned_or_missing_contract_is_never_made_effective(): void
    {
        [$mission, $candidature, $entry] = $this->cashMissionWithAcceptedFace();
        $candidature->update(['status' => CandidatureStatus::InProgress]);

        $this->release($entry, $mission);

        $this->assertSame(0, ImageContract::count());
        ImageContract::markEffectiveForCandidature(999999);
        ImageContract::voidForCandidature(999999);
        $this->assertSame(0, ImageContract::count());
    }

    // ===================================================================
    // Annulation
    // ===================================================================

    public function test_a_refunded_escrow_voids_the_contract(): void
    {
        [$mission, , $entry, $contract] = $this->engagedCash();

        $this->refund($entry, $mission);

        $this->assertNotNull($contract->fresh()->voided_at);
        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_a_dispute_settled_for_the_producer_voids_the_contract(): void
    {
        [$mission, , $entry, $contract] = $this->engagedCash();

        DB::transaction(fn () => $this->payments()->refundToProducer($entry, $mission, 'disputed_resolved_producer'));

        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_an_effective_contract_is_never_voided(): void
    {
        [$mission, $candidature, $entry, $contract] = $this->engagedCash();
        $this->release($entry, $mission);
        $effectiveAt = $contract->fresh()->effective_at;

        $this->refund($entry->fresh(), $mission);
        $candidature->update(['status' => CandidatureStatus::Cancelled]);
        event(new UgcCommissionRefunded($mission));

        $fresh = $contract->fresh();
        $this->assertNull($fresh->voided_at);
        $this->assertTrue($effectiveAt->equalTo($fresh->effective_at));
        $this->assertSame('effective', $fresh->status());
    }

    public function test_unwinding_a_hybrid_slot_voids_the_contract(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        $this->assertTrue($this->payments()->unwindUgcCandidatureSlot($candidature, 'ugc_face_declined'));

        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
        $this->assertSame('void', $contract->fresh()->status());
        $this->assertSame(EscrowStatus::Refunded, $candidature->paymentEntry->fresh()->escrow_status);
    }

    public function test_unwinding_a_product_only_slot_voids_the_contract(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        $this->assertTrue($this->payments()->unwindUgcCandidatureSlot($candidature, 'ugc_face_declined'));

        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_deleting_the_mission_voids_the_contract_and_keeps_it_with_a_null_candidature(): void
    {
        [$mission, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        app(MissionService::class)->deleteMission($mission);

        $this->assertNull(Mission::find($mission->id));
        $fresh = $contract->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->candidature_id);
        $this->assertSame('void', $fresh->status());
    }

    public function test_deleting_the_mission_keeps_an_effective_contract_effective(): void
    {
        [$mission, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature, ['effective_at' => now()]);

        app(MissionService::class)->deleteMission($mission);

        $fresh = $contract->fresh();
        $this->assertNull($fresh->candidature_id);
        $this->assertSame('effective', $fresh->status());
    }

    public function test_a_refunded_commission_voids_the_non_effective_contracts_of_the_mission(): void
    {
        [$mission, $first] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $first->update(['status' => CandidatureStatus::Confirmed]);
        $void = $this->makeCandidatureContract($first);
        $second = Candidature::factory()->create(['mission_id' => $mission->id, 'status' => CandidatureStatus::Confirmed]);
        $effective = $this->makeCandidatureContract($second, ['effective_at' => now()]);
        $otherMission = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $otherContract = $this->makeCandidatureContract($otherMission[1]);

        UgcCommissionRefunded::dispatch($mission);

        $this->assertSame('void', $void->fresh()->status());
        $this->assertSame('effective', $effective->fresh()->status());
        $this->assertSame('signed', $otherContract->fresh()->status());
    }

    public function test_a_candidature_that_ends_voids_its_contract_but_a_completed_one_does_not(): void
    {
        foreach ([CandidatureStatus::Cancelled, CandidatureStatus::Rejected, CandidatureStatus::Expired] as $status) {
            [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
            $candidature->update(['status' => CandidatureStatus::Confirmed]);
            $contract = $this->makeCandidatureContract($candidature);

            $candidature->update(['status' => $status]);

            $this->assertSame('void', $contract->fresh()->status(), $status->value);
        }

        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature);

        $candidature->update(['status' => CandidatureStatus::InProgress]);
        $candidature->update(['status' => CandidatureStatus::Completed]);
        $candidature->update(['message_motivation' => 'Mise à jour sans changement de statut']);

        $this->assertSame('signed', $contract->fresh()->status());
    }

    public function test_a_void_contract_cannot_be_voided_again_or_made_effective(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $contract = $this->makeCandidatureContract($candidature, ['voided_at' => now()->subDay()]);
        $voidedAt = $contract->voided_at;

        ImageContract::voidForCandidature($candidature->id);
        ImageContract::markEffectiveForCandidature($candidature->id);

        $fresh = $contract->fresh();
        $this->assertTrue($voidedAt->equalTo($fresh->voided_at));
        $this->assertNull($fresh->effective_at);
    }
}
