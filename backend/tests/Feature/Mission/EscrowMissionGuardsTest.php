<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Garde-fous argent sur les missions cash (et hybrides UGC) : suppression, release,
 * complétion pendant la fenêtre de contestation, retrait/refus pendant le checkout.
 *
 * Chaque test d'exploit rejoue la requête HTTP que ferait l'attaquant puis vérifie
 * wallets / escrow / statuts. Les tests `test_legit_*` prouvent que les flux légitimes
 * restent ouverts.
 */
class EscrowMissionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    // =====================================================================
    // Exploit 1 : suppression / modification d'une mission cash payée
    // =====================================================================

    public function test_cannot_delete_paid_cash_mission_in_pending_attendance_validation_to_steal_escrow(): void
    {
        [$mission, $faces] = $this->paidCashMission(3);
        $producerBefore = (int) $this->producerUser->fresh()->balance;

        // Étape 1 : marquer une Face absente (aucune garde de date) → PendingAttendanceValidation.
        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [['entry_id' => $faces[0]['entry']->id, 'status' => 'absent']]],
        )->assertOk();
        $this->assertSame(MissionStatus::PendingAttendanceValidation, $mission->fresh()->status);

        // Étape 2 : DELETE pour se faire rembourser tous les escrows.
        $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('missions', ['id' => $mission->id]);
        foreach ($faces as $face) {
            $this->assertSame(EscrowStatus::Locked, $face['entry']->fresh()->escrow_status);
        }
        $this->assertSame($producerBefore, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_cannot_delete_published_mission_that_has_a_paid_cash_payment(): void
    {
        [$mission, $faces] = $this->paidCashMission(2);
        // Mission rouverte (ex. ancien chemin release/reopen) : statut Published mais escrow Locked.
        $mission->update(['status' => MissionStatus::Published]);

        $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertStatus(422)
            ->assertJsonPath('error.details.mission.0', 'Une mission dont le paiement a été effectué ne peut pas être supprimée.');

        $this->assertDatabaseHas('missions', ['id' => $mission->id]);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_refund_ugc_candidature_escrow_is_noop_on_cash_entry(): void
    {
        [$mission, $faces] = $this->paidCashMission(1);
        $candidature = $faces[0]['candidature'];

        DB::transaction(fn () => app(MissionPaymentService::class)
            ->refundUgcCandidatureEscrow($candidature->load('mission'), 'mission_deleted'));

        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_cannot_update_mission_in_pending_attendance_validation(): void
    {
        [$mission] = $this->paidCashMission(2, MissionStatus::PendingAttendanceValidation);

        $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$mission->uuid}", [
                'titre' => 'Titre modifié',
                'description' => 'Description modifiée de la mission...',
                'date_tournage' => now()->addMonths(3)->format('Y-m-d'),
                'profil_recherche' => 'Profil modifié',
                'budget' => 100000,
                'date_limite_candidature' => now()->addWeeks(6)->format('Y-m-d'),
                'nombre_faces_voulu' => 2,
                'type_mission' => 'film',
                'genre_voulu' => 'tous',
                'lieu' => 'Cotonou',
                'duree' => '1 jour',
            ])
            ->assertStatus(422);

        $this->assertNotSame('Titre modifié', $mission->fresh()->titre);
    }

    public function test_legit_producer_can_still_delete_published_mission_without_payment(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('missions', ['id' => $mission->id]);
    }

    public function test_legit_ugc_hybrid_mission_deletion_still_refunds_locked_escrow(): void
    {
        $mission = $this->hybridMission();
        $candidature = $this->hybridCandidature($mission, CandidatureStatus::Accepted);
        $this->hybridEntry($candidature, EscrowStatus::Locked);

        $this->actingAs($this->producerUser)
            ->deleteJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('missions', ['id' => $mission->id]);
        $this->assertSame(14250, (int) $this->producerUser->fresh()->balance);
    }

    // =====================================================================
    // Exploit 2 : release sur une mission cash
    // =====================================================================

    public function test_release_on_cash_mission_is_refused_and_does_not_refund_or_reopen(): void
    {
        [$mission, $faces] = $this->paidCashMission(2);
        $candidature = $faces[0]['candidature'];
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()]);
        $mission->update(['date_limite_candidature' => now()->addWeek()]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/release")
            ->assertStatus(422);

        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_unwind_service_never_reopens_a_mission_with_paid_cash_payment(): void
    {
        [$mission, $faces] = $this->paidCashMission(2);
        $candidature = $faces[0]['candidature'];
        $candidature->update(['status' => CandidatureStatus::Accepted, 'accepted_at' => now()]);
        $mission->update(['date_limite_candidature' => now()->addWeek()]);

        $this->assertFalse(app(MissionPaymentService::class)->unwindUgcCandidatureSlot($candidature, 'producer_released'));

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    public function test_legit_release_on_ugc_hybrid_candidature_still_unwinds(): void
    {
        $mission = $this->hybridMission();
        $candidature = $this->hybridCandidature($mission, CandidatureStatus::Accepted);
        $this->hybridEntry($candidature, EscrowStatus::Locked);
        $mission->update(['status' => MissionStatus::Closed]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/release")
            ->assertOk()
            ->assertJsonPath('data.candidature_status', 'cancelled');

        $this->assertSame(14250, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
    }

    // =====================================================================
    // Exploit 3 : complete pendant la fenêtre de contestation 72 h
    // =====================================================================

    public function test_complete_is_refused_while_dispute_window_is_open(): void
    {
        [$mission, $faces] = $this->paidCashMission(2);

        $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/validate-attendance",
            ['entries' => [
                ['entry_id' => $faces[0]['entry']->id, 'status' => 'absent'],
                ['entry_id' => $faces[1]['entry']->id, 'status' => 'present'],
            ]],
        )->assertOk();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422);

        $this->assertSame(MissionStatus::PendingAttendanceValidation, $mission->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
    }

    public function test_complete_is_refused_while_a_dispute_is_open_even_after_the_window(): void
    {
        [$mission, $faces] = $this->paidCashMission(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Disputed,
            'notified_at' => now()->subHours(100),
        ]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertStatus(422);

        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
    }

    public function test_legit_complete_after_the_dispute_window_settles_absent_faces(): void
    {
        [$mission, $faces] = $this->paidCashMission(2, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now()->subHours(80),
        ]);
        $faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Present]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertOk();

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertSame(EscrowStatus::Refunded, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(EscrowStatus::Released, $faces[1]['entry']->fresh()->escrow_status);
        $this->assertSame(90000, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(90000, (int) $faces[1]['faceUser']->fresh()->balance);
    }

    public function test_legit_complete_does_not_pay_again_an_entry_already_settled(): void
    {
        [$mission, $faces] = $this->paidCashMission(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Present]);

        // Une complétion concurrente a déjà réglé l'entry : elle ne doit pas être repayée.
        $faces[0]['entry']->update(['escrow_status' => EscrowStatus::Released, 'released_at' => now()]);
        DB::table('users')->where('id', $faces[0]['faceUser']->id)->update(['balance' => 90000]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/complete")
            ->assertOk();

        $this->assertSame(90000, (int) $faces[0]['faceUser']->fresh()->balance);
        $this->assertSame(0, WalletTransaction::count());
    }

    // =====================================================================
    // Exploit 4 : retrait Face / refus Producteur pendant le checkout cash
    // =====================================================================

    public function test_face_cannot_withdraw_candidature_selected_in_pending_cash_checkout(): void
    {
        [$mission, $faces] = $this->pendingCheckoutMission(3);
        $target = $faces[0];

        $this->actingAs($target['faceUser'])
            ->postJson("/api/v1/face/candidatures/{$target['candidature']->uuid}/cancel")
            ->assertStatus(422);

        $this->assertSame(CandidatureStatus::Pending, $target['candidature']->fresh()->status);
        $this->assertDatabaseHas('mission_payment_candidatures', ['id' => $target['entry']->id]);
        $this->assertSame(3, MissionPaymentCandidature::count());
    }

    public function test_producer_cannot_reject_candidature_selected_in_pending_cash_checkout(): void
    {
        [$mission, $faces] = $this->pendingCheckoutMission(2);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$faces[0]['candidature']->uuid}/reject")
            ->assertStatus(422);

        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
        $this->assertDatabaseHas('mission_payment_candidatures', ['id' => $faces[0]['entry']->id]);
    }

    public function test_mark_ugc_candidature_failed_refuses_cash_entries(): void
    {
        [$mission, $faces] = $this->pendingCheckoutMission(2);

        app(MissionPaymentService::class)->markUgcMissionCandidatureFailed($faces[0]['entry'], 'face_cancelled_pending');

        $this->assertDatabaseHas('mission_payment_candidatures', ['id' => $faces[0]['entry']->id]);
    }

    public function test_legit_face_can_withdraw_candidature_outside_of_the_selection(): void
    {
        [$mission, $faces] = $this->pendingCheckoutMission(1);

        $otherFace = Face::factory()->create();
        $otherUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $otherFace->id]);
        $other = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $otherFace->id,
            'status' => CandidatureStatus::Pending,
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/face/candidatures/{$other->uuid}/cancel")
            ->assertOk();

        $this->assertSame(CandidatureStatus::Cancelled, $other->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
    }

    // =====================================================================
    // Exploit 5 : refus d'une candidature hybride avec paiement en vol
    // =====================================================================

    public function test_producer_cannot_reject_hybrid_candidature_with_inflight_payment(): void
    {
        $mission = $this->hybridMission();
        $candidature = $this->hybridCandidature($mission, CandidatureStatus::Pending);
        $entry = $this->hybridEntry($candidature, EscrowStatus::Pending, '8500');

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$candidature->uuid}/reject")
            ->assertStatus(422);

        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
        $this->assertSame(EscrowStatus::Pending, $entry->fresh()->escrow_status);
    }

    public function test_late_approval_on_unavailable_candidature_refunds_the_producer(): void
    {
        $mission = $this->hybridMission();
        $candidature = $this->hybridCandidature($mission, CandidatureStatus::Rejected);
        $entry = $this->hybridEntry($candidature, EscrowStatus::Pending, '8500');

        app(MissionPaymentService::class)->markUgcMissionCandidaturePaid($entry, 'ref_late');

        $this->assertSame(EscrowStatus::Refunded, $entry->fresh()->escrow_status);
        $this->assertSame(14250, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(CandidatureStatus::Rejected, $candidature->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['type' => 'refund', 'amount' => 14250]);

        // Idempotence : un re-jeu ne recrédite pas.
        app(MissionPaymentService::class)->markUgcMissionCandidaturePaid($entry->fresh(), 'ref_late');
        $this->assertSame(14250, (int) $this->producerUser->fresh()->balance);
    }

    public function test_legit_face_cancel_of_hybrid_pending_candidature_without_payment_works(): void
    {
        $mission = $this->hybridMission();
        $candidature = $this->hybridCandidature($mission, CandidatureStatus::Pending);
        $faceUser = User::query()->where('userable_id', $candidature->face_id)->where('userable_type', Face::class)->firstOrFail();

        $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/cancel")
            ->assertOk();

        $this->assertSame(CandidatureStatus::Cancelled, $candidature->fresh()->status);
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /**
     * Mission cash payée : MissionPayment Paid (N × 100 000 / net Face 90 000) + N entries
     * Locked/Pending-attendance + N candidatures Confirmed.
     *
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function paidCashMission(int $faceCount, MissionStatus $status = MissionStatus::Closed): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        if ($status !== MissionStatus::Closed) {
            $mission->update(['status' => $status]);
        }

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $faceCount,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faceCount,
            'commission_producteur' => 10000 * $faceCount,
            'montant_total_producteur' => 110000 * $faceCount,
            'commission_faces_total' => 10000 * $faceCount,
            'montant_total_faces' => 90000 * $faceCount,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        return [$mission, $this->makeFaces($mission, $payment, $faceCount, CandidatureStatus::Confirmed, EscrowStatus::Locked)];
    }

    /**
     * Mission en checkout cash : pending_payment + MissionPayment Pending + entries Pending
     * (candidatures encore Pending).
     *
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function pendingCheckoutMission(int $faceCount): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $faceCount,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faceCount,
            'commission_producteur' => 10000 * $faceCount,
            'montant_total_producteur' => 110000 * $faceCount,
            'commission_faces_total' => 10000 * $faceCount,
            'montant_total_faces' => 90000 * $faceCount,
            'fedapay_transaction_id' => 777001,
            'status' => MissionPaymentStatus::Pending,
        ]);

        return [$mission, $this->makeFaces($mission, $payment, $faceCount, CandidatureStatus::Pending, EscrowStatus::Pending)];
    }

    /**
     * @return list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>
     */
    private function makeFaces(Mission $mission, MissionPayment $payment, int $count, CandidatureStatus $candidatureStatus, EscrowStatus $escrow): array
    {
        $faces = [];

        for ($i = 0; $i < $count; $i++) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => $candidatureStatus,
            ]);
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => $escrow,
                'locked_at' => $escrow === EscrowStatus::Locked ? now() : null,
            ]);

            $faces[] = compact('candidature', 'entry', 'faceUser', 'face');
        }

        return $faces;
    }

    private function hybridMission(): Mission
    {
        return $this->producer->missions()->create([
            'titre' => 'Appel UGC hybride',
            'description' => 'Brief',
            'date_tournage' => null,
            'lieu' => null,
            'duree' => null,
            'profil_recherche' => 'Créatrices',
            'budget' => 15000,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => MissionType::Ugc->value,
            'genre_voulu' => 'tous',
            'status' => MissionStatus::Published,
            'type_compensation' => CompensationType::Hybrid->value,
            'nom_produit' => 'Sneakers',
            'valeur_produit' => 50000,
            'nombre_videos' => 3,
            'montant_remuneration' => 15000,
        ]);
    }

    private function hybridCandidature(Mission $mission, CandidatureStatus $status): Candidature
    {
        $face = Face::factory()->create(['sexe' => 'femme']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
            'accepted_at' => $status === CandidatureStatus::Accepted ? now() : null,
        ]);
    }

    private function hybridEntry(Candidature $candidature, EscrowStatus $escrow, ?string $txn = null): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 14250,
            'escrow_status' => $escrow,
            'locked_at' => $escrow === EscrowStatus::Locked ? now() : null,
            'fedapay_transaction_id' => $txn ?? ('99'.$candidature->id),
        ]);
    }
}
