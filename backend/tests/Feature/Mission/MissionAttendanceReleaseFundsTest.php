<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Mail\MissionCompletedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FIX-26.2 — Tests d'invariants pour le routing par `attendance_status` dans
 * `MissionPaymentService::releaseFunds`. Tous les appels `releaseFunds` sont wrappés
 * dans `DB::transaction(...)` pour respecter le contrat documenté
 * (`MissionPaymentService.php:654-655` : « MUST be called inside an existing DB::transaction() »).
 */
class MissionAttendanceReleaseFundsTest extends TestCase
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

    /**
     * Helper: create a `Closed` mission with a paid `MissionPayment` and N entries
     * Locked + attendance_status = pending (DB default per FIX-26.1 schema).
     *
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function createPaidMissionWithFaces(int $faceCount): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        /** @var MissionPayment $payment */
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

        $rows = [];

        for ($i = 0; $i < $faceCount; $i++) {
            /** @var Face $face */
            $face = Face::factory()->create([
                'prenom' => "Face{$i}",
                'nom' => 'Test',
            ]);

            /** @var User $faceUser */
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "face{$i}@example.test",
            ]);

            /** @var Candidature $candidature */
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);

            /** @var MissionPaymentCandidature $entry */
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now(),
                // attendance_status defaults to pending (FIX-26.1 schema). Tests override
                // with $entry->update([...]) when an explicit value is needed.
            ]);

            $rows[] = [
                'candidature' => $candidature,
                'entry' => $entry,
                'faceUser' => $faceUser,
                'face' => $face,
            ];
        }

        return [$mission, $rows];
    }

    private function service(): MissionPaymentService
    {
        return $this->app->make(MissionPaymentService::class);
    }

    /**
     * AC #3 — Given an entry Locked + present, when releaseFunds runs, then the Face
     * wallet is credited, candidature → Completed, notif + mail queued, and an
     * EscrowRelease FinancialEvent is recorded with the deterministic idempotency_key.
     */
    public function test_release_funds_credits_face_for_present_entry(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(1);
        $row = $rows[0];
        $row['entry']->update(['attendance_status' => AttendanceStatus::Present]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        $row['faceUser']->refresh();
        $this->assertSame(90000, $row['faceUser']->balance);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $row['entry']->id,
            'attendance_status' => 'present',
            'escrow_status' => 'released',
        ]);
        $this->assertNotNull($row['entry']->fresh()->released_at);

        $this->assertDatabaseHas('candidatures', [
            'id' => $row['candidature']->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $row['faceUser']->id,
            'type' => 'mission_completed',
        ]);

        Mail::assertQueued(
            MissionCompletedMail::class,
            fn (MissionCompletedMail $mail): bool => $mail->hasTo($row['faceUser']->email)
                && $mail->amount === 90000,
        );

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$row['entry']->id}",
            'type' => FinancialEventType::EscrowRelease->value,
            'booking_id' => null,
            'amount' => 90000,
            'status' => 'completed',
        ]);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$row['entry']->id}")->firstOrFail();
        $this->assertSame('attendance_present', $event->metadata['reason'] ?? null);
        $this->assertSame($row['entry']->id, $event->metadata['mission_payment_candidature_id'] ?? null);
        $this->assertSame($row['entry']->face_id, $event->metadata['face_id'] ?? null);
    }

    /**
     * AC #4 — Given an entry Locked + absent, when releaseFunds runs, then the Producer
     * wallet is credited (100 %), the entry → Refunded, candidature is NOT touched,
     * no Face notif + no mail, and a Refund FinancialEvent is recorded.
     */
    public function test_release_funds_refunds_producer_for_absent_entry(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(1);
        $row = $rows[0];
        $row['entry']->update(['attendance_status' => AttendanceStatus::Absent]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        $this->producerUser->refresh();
        $this->assertSame(90000, $this->producerUser->balance);

        $row['faceUser']->refresh();
        $this->assertSame(0, $row['faceUser']->balance);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $row['entry']->id,
            'attendance_status' => 'absent',
            'escrow_status' => 'refunded',
        ]);
        $this->assertNotNull($row['entry']->fresh()->refunded_at);

        // Candidature unchanged (still Confirmed).
        $this->assertDatabaseHas('candidatures', [
            'id' => $row['candidature']->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $row['faceUser']->id,
            'type' => 'mission_completed',
        ]);

        Mail::assertNothingQueued();

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$row['entry']->id}",
            'type' => FinancialEventType::Refund->value,
            'booking_id' => null,
            'amount' => 90000,
            'status' => 'completed',
        ]);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_refund:{$row['entry']->id}")->firstOrFail();
        $this->assertSame('attendance_absent', $event->metadata['reason'] ?? null);
        $this->assertSame(100, $event->metadata['refund_percentage'] ?? null);
    }

    /**
     * AC #5 — Given an entry Locked + disputed, when releaseFunds runs, then the entry
     * is untouched, no wallet movement, no notif, no mail, no FinancialEvent.
     */
    public function test_release_funds_skips_disputed_entry(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(1);
        $row = $rows[0];
        $row['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        $this->producerUser->refresh();
        $this->assertSame(0, $this->producerUser->balance);

        $row['faceUser']->refresh();
        $this->assertSame(0, $row['faceUser']->balance);

        $entry = $row['entry']->fresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $entry->attendance_status);
        $this->assertNull($entry->released_at);
        $this->assertNull($entry->refunded_at);

        Mail::assertNothingQueued();

        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$row['entry']->id}",
        ]);
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$row['entry']->id}",
        ]);
    }

    /**
     * AC #6 + #9 — Given an entry Locked + pending passed directly to releaseFunds
     * (without the completeMission bridge), when it runs, then a RuntimeException is
     * thrown, the parent transaction rolls back, and no wallet/event mutation persists.
     */
    public function test_release_funds_throws_runtime_exception_for_pending_entry(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(1);
        $row = $rows[0];
        // Entry stays at the default attendance_status = pending.

        try {
            DB::transaction(fn () => $this->service()->releaseFunds($mission));
            $this->fail('Expected RuntimeException for attendance_status=pending.');
        } catch (\RuntimeException $e) {
            $this->assertMatchesRegularExpression('/attendance_status=pending/', $e->getMessage());
        }

        $entry = $row['entry']->fresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status);
        $this->assertNull($entry->released_at);
        $this->assertNull($entry->refunded_at);

        $row['faceUser']->refresh();
        $this->assertSame(0, $row['faceUser']->balance);

        $this->producerUser->refresh();
        $this->assertSame(0, $this->producerUser->balance);

        $this->assertSame(0, FinancialEvent::count());
    }

    /**
     * AC #2 + #8 — Given 3 confirmed Faces with explicit present/absent/disputed,
     * when releaseFunds runs, then the routing applies independently to each entry,
     * the disputed entry is left intact, and the right number of FinancialEvents land.
     */
    public function test_release_funds_handles_mixed_attendance_three_faces(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(3);

        $rows[0]['entry']->update(['attendance_status' => AttendanceStatus::Present]);
        $rows[1]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);
        $rows[2]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        // Face 0 (present) — released.
        $rows[0]['faceUser']->refresh();
        $this->assertSame(90000, $rows[0]['faceUser']->balance);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $rows[0]['entry']->id,
            'escrow_status' => 'released',
        ]);
        $this->assertDatabaseHas('candidatures', [
            'id' => $rows[0]['candidature']->id,
            'status' => 'completed',
        ]);

        // Face 1 (absent) — refunded to producer.
        $rows[1]['faceUser']->refresh();
        $this->assertSame(0, $rows[1]['faceUser']->balance);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $rows[1]['entry']->id,
            'escrow_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('candidatures', [
            'id' => $rows[1]['candidature']->id,
            'status' => 'confirmed',
        ]);

        // Face 2 (disputed) — untouched.
        $rows[2]['faceUser']->refresh();
        $this->assertSame(0, $rows[2]['faceUser']->balance);
        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $rows[2]['entry']->id,
            'escrow_status' => 'locked',
            'attendance_status' => 'disputed',
        ]);

        // Producer received 1 × montant_face_recoit (refund of Face 1).
        $this->producerUser->refresh();
        $this->assertSame(90000, $this->producerUser->balance);

        $this->assertSame(
            1,
            FinancialEvent::where('type', FinancialEventType::EscrowRelease)->count(),
        );
        $this->assertSame(
            1,
            FinancialEvent::where('type', FinancialEventType::Refund)->count(),
        );
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$rows[2]['entry']->id}",
        ]);
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$rows[2]['entry']->id}",
        ]);
    }

    /**
     * AC #7 — Given two consecutive releaseFunds calls (retry-after-commit pattern),
     * when both run in their own DB::transaction, then no double-credit and no double
     * FinancialEvent (the WHERE escrow_status = Locked filter naturally drops processed
     * entries on the second pass).
     */
    public function test_release_funds_is_idempotent_when_called_twice(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(2);
        $rows[0]['entry']->update(['attendance_status' => AttendanceStatus::Present]);
        $rows[1]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));
        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        $rows[0]['faceUser']->refresh();
        $this->assertSame(90000, $rows[0]['faceUser']->balance);

        $this->producerUser->refresh();
        $this->assertSame(90000, $this->producerUser->balance);

        $this->assertSame(2, FinancialEvent::count());
    }

    /**
     * AC #3 + #4 — Verify the deterministic idempotency_key pattern explicitly so that
     * future code (FIX-26.3 retries, audit reports) can rely on it.
     */
    public function test_release_funds_records_financial_event_with_correct_idempotency_key_pattern(): void
    {
        [$mission, $rows] = $this->createPaidMissionWithFaces(2);
        $rows[0]['entry']->update(['attendance_status' => AttendanceStatus::Present]);
        $rows[1]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);

        DB::transaction(fn () => $this->service()->releaseFunds($mission));

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$rows[0]['entry']->id}",
            'type' => FinancialEventType::EscrowRelease->value,
            'booking_id' => null,
            'amount' => 90000,
        ]);

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_refund:{$rows[1]['entry']->id}",
            'type' => FinancialEventType::Refund->value,
            'booking_id' => null,
            'amount' => 90000,
        ]);
    }
}
