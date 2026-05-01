<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Events\MissionAttendanceMarkedAbsent;
use App\Mail\MissionCompletedMail;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionAttendanceService;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MissionAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MissionAttendanceService $service;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->service = app(MissionAttendanceService::class);
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array{0: Mission, 1: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function createPaidMissionWithFaces(
        int $faceCount,
        MissionStatus $missionStatus = MissionStatus::Closed,
    ): array {
        $factoryMethod = match ($missionStatus) {
            MissionStatus::Closed, MissionStatus::PendingAttendanceValidation => 'closed',
            default => throw new \InvalidArgumentException("Unsupported mission status for fixture: {$missionStatus->value}"),
        };

        /** @var Mission $mission */
        $mission = Mission::factory()->{$factoryMethod}()->create([
            'producer_id' => $this->producer->id,
        ]);

        if ($missionStatus === MissionStatus::PendingAttendanceValidation) {
            $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
            $mission->refresh();
        }

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

        $faces = [];

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
                'email' => "mission{$mission->id}.face{$i}@example.test",
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
            ]);

            $faces[] = [
                'candidature' => $candidature,
                'entry' => $entry,
                'faceUser' => $faceUser,
                'face' => $face,
            ];
        }

        return [$mission, $faces];
    }

    private function createAdmin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_mark_attendance_releases_present_entries_and_keeps_absent_locked(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3);

        $result = $this->service->markAttendance($mission, [
            $faces[0]['entry']->id => 'present',
            $faces[1]['entry']->id => 'absent',
            $faces[2]['entry']->id => 'present',
        ], $this->producerUser);

        $this->assertSame(MissionStatus::PendingAttendanceValidation, $result->status);

        $entry0 = $faces[0]['entry']->fresh();
        $entry1 = $faces[1]['entry']->fresh();
        $entry2 = $faces[2]['entry']->fresh();

        $this->assertSame(EscrowStatus::Released, $entry0->escrow_status);
        $this->assertSame(AttendanceStatus::Present, $entry0->attendance_status);
        $this->assertNotNull($entry0->released_at);

        $this->assertSame(EscrowStatus::Locked, $entry1->escrow_status);
        $this->assertSame(AttendanceStatus::Absent, $entry1->attendance_status);
        $this->assertNull($entry1->released_at);
        $this->assertNull($entry1->refunded_at);
        $this->assertNotNull($entry1->notified_at);

        $this->assertSame(EscrowStatus::Released, $entry2->escrow_status);
        $this->assertSame(AttendanceStatus::Present, $entry2->attendance_status);

        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(0, $faces[1]['faceUser']->refresh()->balance);
        $this->assertSame(90000, $faces[2]['faceUser']->refresh()->balance);
        $this->assertSame(0, $this->producerUser->refresh()->balance);
        $this->assertSame(2, FinancialEvent::count());

        $this->assertDatabaseHas('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$faces[0]['entry']->id}",
            'type' => FinancialEventType::EscrowRelease->value,
        ]);
        Mail::assertQueued(MissionCompletedMail::class, 2);
    }

    public function test_mark_attendance_completes_mission_when_all_entries_present(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2);

        $this->service->markAttendance($mission, [
            $faces[0]['entry']->id => 'present',
            $faces[1]['entry']->id => 'present',
        ], $this->producerUser);

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertSame(EscrowStatus::Released, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(EscrowStatus::Released, $faces[1]['entry']->fresh()->escrow_status);
        $this->assertTrue(
            Notification::where('user_id', $this->producerUser->id)
                ->where('type', 'mission_completed_producer')
                ->exists(),
        );
    }

    public function test_mark_attendance_rejects_non_owner_actor(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        try {
            $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'present'], $otherProducerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    public function test_mark_attendance_rejects_invalid_mission_status(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $mission->update(['status' => MissionStatus::Published]);

        try {
            $this->service->markAttendance($mission->fresh(), [$faces[0]['entry']->id => 'present'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
    }

    public function test_mark_attendance_rejects_entry_not_belonging_to_mission(): void
    {
        [$mission1] = $this->createPaidMissionWithFaces(1);
        [, $faces2] = $this->createPaidMissionWithFaces(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not belong to mission');

        $this->service->markAttendance($mission1, [$faces2[0]['entry']->id => 'present'], $this->producerUser);
    }

    public function test_mark_attendance_rejects_invalid_status_value(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        try {
            $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'disputed'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        try {
            $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'foo'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
    }

    public function test_mark_attendance_rejects_non_integer_entry_keys(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        try {
            $this->service->markAttendance($mission, ['abc' => 'present'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
    }

    public function test_mark_attendance_rejects_mission_without_paid_payment(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        $mission->payment?->update(['status' => MissionPaymentStatus::Pending]);

        try {
            $this->service->markAttendance($mission->fresh(), [$faces[0]['entry']->id => 'present'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(0, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(0, FinancialEvent::count());

        /** @var Mission $missionWithoutPayment */
        $missionWithoutPayment = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);

        try {
            $this->service->markAttendance($missionWithoutPayment, [999999 => 'present'], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(MissionStatus::Closed, $missionWithoutPayment->fresh()->status);
    }

    public function test_mark_attendance_rejects_empty_input(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        try {
            $this->service->markAttendance($mission, [], $this->producerUser);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
    }

    public function test_mark_attendance_revalidates_mission_status_under_lock(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);
        Mission::where('id', $mission->id)->update(['status' => MissionStatus::Published]);

        try {
            $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'present'], $this->producerUser);
            $this->fail('Expected ValidationException raised under lock');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Pending, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(0, FinancialEvent::count());
    }

    public function test_mark_attendance_replays_batch_on_already_tranched_entries_is_no_op(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3, MissionStatus::PendingAttendanceValidation);
        $notifiedAt = now()->subHour();
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Present]);
        $faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Absent, 'notified_at' => $notifiedAt]);
        $faces[2]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        $this->service->markAttendance($mission, [
            $faces[0]['entry']->id => 'present',
            $faces[1]['entry']->id => 'absent',
        ], $this->producerUser);

        $this->assertSame(AttendanceStatus::Present, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Absent, $faces[1]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $faces[1]['entry']->fresh()->escrow_status);
        $this->assertSame($notifiedAt->toDateTimeString(), $faces[1]['entry']->fresh()->notified_at->toDateTimeString());
        $this->assertSame(AttendanceStatus::Disputed, $faces[2]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $faces[2]['entry']->fresh()->escrow_status);
        $this->assertSame(0, FinancialEvent::count());
        $this->assertSame(MissionStatus::PendingAttendanceValidation, $mission->fresh()->status);
    }

    public function test_mark_attendance_completes_mission_with_locked_disputed_present(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);
        $faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'present'], $this->producerUser);

        $this->assertSame(EscrowStatus::Released, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Present, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $faces[1]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $faces[1]['entry']->fresh()->attendance_status);
        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    public function test_mark_attendance_is_idempotent(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'present'], $this->producerUser);
        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(1, FinancialEvent::count());

        $this->service->markAttendance($mission->fresh(), [$faces[0]['entry']->id => 'present'], $this->producerUser);

        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(1, FinancialEvent::count());
        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    public function test_mark_attendance_sets_notified_at_for_absent_and_does_not_overwrite_on_idempotent_call(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'absent'], $this->producerUser);
        $firstNotifiedAt = $faces[0]['entry']->fresh()->notified_at;

        $this->assertNotNull($firstNotifiedAt);
        $this->assertSame(AttendanceStatus::Absent, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);

        $this->travel(10)->seconds();
        $this->service->markAttendance($mission->fresh(), [$faces[0]['entry']->id => 'absent'], $this->producerUser);

        $this->assertTrue($faces[0]['entry']->fresh()->notified_at->equalTo($firstNotifiedAt));
    }

    public function test_mark_attendance_dispatches_one_event_per_newly_absent_entry(): void
    {
        Event::fake([MissionAttendanceMarkedAbsent::class]);
        [$mission, $faces] = $this->createPaidMissionWithFaces(3);

        $this->service->markAttendance(
            $mission,
            [
                $faces[0]['entry']->id => 'present',
                $faces[1]['entry']->id => 'absent',
                $faces[2]['entry']->id => 'absent',
            ],
            $this->producerUser,
        );

        Event::assertDispatched(MissionAttendanceMarkedAbsent::class, 2);
        Event::assertDispatched(
            MissionAttendanceMarkedAbsent::class,
            fn (MissionAttendanceMarkedAbsent $event): bool => $event->entry->id === $faces[1]['entry']->id,
        );
        Event::assertDispatched(
            MissionAttendanceMarkedAbsent::class,
            fn (MissionAttendanceMarkedAbsent $event): bool => $event->entry->id === $faces[2]['entry']->id,
        );

        $dispatchedIds = array_map(
            fn (MissionAttendanceMarkedAbsent $event): int => $event->entry->id,
            Event::dispatched(MissionAttendanceMarkedAbsent::class)
                ->map(fn (array $args): MissionAttendanceMarkedAbsent => $args[0])
                ->all(),
        );
        $this->assertSame(
            [$faces[1]['entry']->id, $faces[2]['entry']->id],
            $dispatchedIds,
            'Events must be dispatched in foreach (ksort) order: entry2 then entry3.',
        );
    }

    public function test_mark_attendance_does_not_dispatch_event_when_no_absence(): void
    {
        Event::fake([MissionAttendanceMarkedAbsent::class]);
        [$mission, $faces] = $this->createPaidMissionWithFaces(2);

        $this->service->markAttendance(
            $mission,
            [
                $faces[0]['entry']->id => 'present',
                $faces[1]['entry']->id => 'present',
            ],
            $this->producerUser,
        );

        Event::assertNotDispatched(MissionAttendanceMarkedAbsent::class);
    }

    public function test_mark_attendance_does_not_re_dispatch_event_on_idempotent_recall(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        Event::fake([MissionAttendanceMarkedAbsent::class]);
        $this->service->markAttendance(
            $mission,
            [$faces[0]['entry']->id => 'absent'],
            $this->producerUser,
        );
        Event::assertDispatched(MissionAttendanceMarkedAbsent::class, 1);
        $firstNotifiedAt = $faces[0]['entry']->fresh()->notified_at;
        $this->assertNotNull($firstNotifiedAt);

        $this->travel(10)->seconds();
        Event::fake([MissionAttendanceMarkedAbsent::class]);
        $this->service->markAttendance(
            $mission->fresh(),
            [$faces[0]['entry']->id => 'absent'],
            $this->producerUser,
        );
        Event::assertNotDispatched(MissionAttendanceMarkedAbsent::class);
        $this->assertTrue(
            $faces[0]['entry']->fresh()->notified_at->equalTo($firstNotifiedAt),
            'notified_at must remain the original timestamp on idempotent recall.',
        );
    }

    public function test_mark_attendance_does_not_dispatch_event_when_transaction_rolls_back(): void
    {
        Event::fake([MissionAttendanceMarkedAbsent::class]);
        [$mission1, $faces1] = $this->createPaidMissionWithFaces(1);
        [, $faces2] = $this->createPaidMissionWithFaces(1);

        try {
            $this->service->markAttendance(
                $mission1,
                [
                    $faces1[0]['entry']->id => 'absent',
                    $faces2[0]['entry']->id => 'absent',
                ],
                $this->producerUser,
            );
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // Expected ownership mismatch rollback.
        }

        Event::assertNotDispatched(MissionAttendanceMarkedAbsent::class);
        $this->assertSame(AttendanceStatus::Pending, $faces1[0]['entry']->fresh()->attendance_status);
    }

    public function test_mark_attendance_swallows_dispatch_failure_via_log_warning(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        Event::listen(MissionAttendanceMarkedAbsent::class, function (): void {
            throw new \RuntimeException('Listener crashed');
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'MissionAttendanceMarkedAbsent dispatch failed'
                && $context['entry_id'] === $faces[0]['entry']->id
                && $context['mission_id'] === $mission->id
                && isset($context['error']));

        $freshMission = $this->service->markAttendance(
            $mission,
            [$faces[0]['entry']->id => 'absent'],
            $this->producerUser,
        );

        $this->assertSame(MissionStatus::PendingAttendanceValidation, $freshMission->status);
        $this->assertSame(AttendanceStatus::Absent, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertNotNull($faces[0]['entry']->fresh()->notified_at);
    }

    public function test_dispute_attendance_flips_absent_to_disputed(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now(),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with(
                'MissionAttendanceService::disputeAttendance — entry disputed by Face',
                \Mockery::on(fn (array $context): bool => $context['entry_id'] === $faces[0]['entry']->id
                    && $context['face_id'] === $faces[0]['entry']->face_id
                    && $context['mission_id'] === $faces[0]['entry']->missionPayment->mission_id),
            );

        $this->service->disputeAttendance($faces[0]['entry'], $faces[0]['faceUser']);

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(AttendanceStatus::Disputed, $entry->attendance_status);
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertNull($entry->released_at);
        $this->assertNull($entry->refunded_at);
        $this->assertSame(0, FinancialEvent::count());
    }

    public function test_dispute_attendance_rejects_non_owner_face(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);

        try {
            $this->service->disputeAttendance($faces[0]['entry'], $faces[1]['faceUser']);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(AttendanceStatus::Absent, $faces[0]['entry']->fresh()->attendance_status);
    }

    public function test_dispute_attendance_rejects_non_face_actor(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);

        $this->expectException(ValidationException::class);

        $this->service->disputeAttendance($faces[0]['entry'], $this->producerUser);
    }

    public function test_dispute_attendance_rejects_entry_not_in_absent_state(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        try {
            $this->service->disputeAttendance($faces[0]['entry'], $faces[0]['faceUser']);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Present,
            'escrow_status' => EscrowStatus::Released,
        ]);

        try {
            $this->service->disputeAttendance($faces[0]['entry']->fresh(), $faces[0]['faceUser']);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            // Expected.
        }

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(AttendanceStatus::Present, $entry->attendance_status);
        $this->assertSame(EscrowStatus::Released, $entry->escrow_status);
    }

    public function test_resolve_dispute_favor_face_releases_to_face_with_correct_metadata(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);
        $admin = $this->createAdmin();

        $this->service->resolveDispute($faces[0]['entry'], DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Released, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $entry->attendance_status);
        $this->assertNotNull($entry->released_at);
        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(0, $this->producerUser->refresh()->balance);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$faces[0]['entry']->id}")->firstOrFail();
        $this->assertSame(FinancialEventType::EscrowRelease, $event->type);
        $this->assertSame('disputed_resolved_face', $event->metadata['reason'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
        $this->assertSame($admin->role->value, $event->metadata['admin_role'] ?? null);
        $this->assertSame('Note admin de test', $event->metadata['admin_notes'] ?? null);
        $this->assertSame('favor_face', $event->metadata['outcome'] ?? null);
        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
    }

    public function test_resolve_dispute_favor_producer_refunds_with_correct_metadata(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);
        $admin = $this->createAdmin();

        $this->service->resolveDispute($faces[0]['entry'], DisputeResolutionOutcome::FavorProducer, $admin, 'Note admin de test');

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $entry->attendance_status);
        $this->assertNotNull($entry->refunded_at);
        $this->assertSame(90000, $this->producerUser->refresh()->balance);
        $this->assertSame(0, $faces[0]['faceUser']->refresh()->balance);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_refund:{$faces[0]['entry']->id}")->firstOrFail();
        $this->assertSame(FinancialEventType::Refund, $event->type);
        $this->assertSame('disputed_resolved_producer', $event->metadata['reason'] ?? null);
        $this->assertSame(100, $event->metadata['refund_percentage'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
        $this->assertSame($admin->role->value, $event->metadata['admin_role'] ?? null);
        $this->assertSame('Note admin de test', $event->metadata['admin_notes'] ?? null);
        $this->assertSame('favor_producer', $event->metadata['outcome'] ?? null);
    }

    public function test_resolve_dispute_rejects_entry_not_in_disputed_state(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);
        $admin = $this->createAdmin();

        $this->expectException(ValidationException::class);

        $this->service->resolveDispute($faces[0]['entry'], DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');
    }

    public function test_resolve_dispute_is_idempotent_via_state_guard(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);
        $admin = $this->createAdmin();

        $this->service->resolveDispute($faces[0]['entry'], DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');
        $this->assertSame(EscrowStatus::Released, $faces[0]['entry']->fresh()->escrow_status);

        try {
            $this->service->resolveDispute($faces[0]['entry']->fresh(), DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');
            $this->fail('Expected ValidationException on second resolveDispute call');
        } catch (ValidationException) {
            // Expected.
        }

        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(1, FinancialEvent::count());
    }

    public function test_resolve_dispute_completes_mission_when_last_locked_entry_resolved(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3, MissionStatus::PendingAttendanceValidation);
        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Present,
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now(),
        ]);
        $faces[1]['entry']->update([
            'attendance_status' => AttendanceStatus::Absent,
            'escrow_status' => EscrowStatus::Refunded,
            'refunded_at' => now(),
        ]);
        $faces[2]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);
        $admin = $this->createAdmin();

        $this->service->resolveDispute($faces[2]['entry'], DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertTrue(
            Notification::where('user_id', $this->producerUser->id)
                ->where('type', 'mission_completed_producer')
                ->exists(),
        );
    }

    public function test_resolve_dispute_keeps_mission_in_pending_attendance_validation_when_others_locked(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(3, MissionStatus::PendingAttendanceValidation);
        // Entry 0: still Locked + Pending (blocking)
        // Entry 1: Locked + Absent (blocking)
        $faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);
        // Entry 2: Locked + Disputed (the one we resolve)
        $faces[2]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);
        $admin = $this->createAdmin();

        $this->service->resolveDispute($faces[2]['entry'], DisputeResolutionOutcome::FavorFace, $admin, 'Note admin de test');

        // Mission MUST stay in PendingAttendanceValidation because entry 0 (Locked+Pending)
        // and entry 1 (Locked+Absent) are still blocking per tryCompleteIfReady's filter.
        $this->assertSame(
            MissionStatus::PendingAttendanceValidation,
            $mission->fresh()->status,
            'Mission should NOT complete while other entries are Locked + (Pending|Absent).',
        );
        // The disputed entry was resolved → Released (audit attendance_status stays Disputed).
        $this->assertSame(EscrowStatus::Released, $faces[2]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $faces[2]['entry']->fresh()->attendance_status);
        // notifyProducerOnCompletion NOT called (mission didn't transition to Completed).
        $this->assertFalse(
            Notification::where('user_id', $this->producerUser->id)
                ->where('type', 'mission_completed_producer')
                ->exists(),
        );
    }

    public function test_mission_can_complete_with_locked_disputed_entries_remaining(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2);
        $faces[1]['entry']->update(['attendance_status' => AttendanceStatus::Disputed]);

        $this->service->markAttendance($mission, [$faces[0]['entry']->id => 'present'], $this->producerUser);

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[1]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $faces[1]['entry']->fresh()->attendance_status);
    }

    public function test_complete_mission_legacy_path_remains_unchanged(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1);

        /** @var MissionService $missionService */
        $missionService = app(MissionService::class);
        $missionService->completeMission($mission);

        $this->assertSame(MissionStatus::Completed, $mission->fresh()->status);
        $this->assertSame(AttendanceStatus::Present, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(EscrowStatus::Released, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
    }

    // ============================================================
    // FIX-26.6 — Service::autoValidatePendingAsPresent
    // ============================================================

    public function test_auto_validate_pending_as_present_releases_pending_entries_and_completes_mission(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $result = $this->service->autoValidatePendingAsPresent($mission);

        $this->assertSame(MissionStatus::Completed, $result->status);

        foreach ($faces as $faceData) {
            $this->assertDatabaseHas('mission_payment_candidatures', [
                'id' => $faceData['entry']->id,
                'attendance_status' => AttendanceStatus::Present->value,
                'escrow_status' => EscrowStatus::Released->value,
            ]);

            $this->assertSame(90000, $faceData['faceUser']->refresh()->balance);

            $this->assertDatabaseHas('financial_events', [
                'type' => FinancialEventType::EscrowRelease->value,
                'idempotency_key' => "mission_attendance_escrow_release:{$faceData['entry']->id}",
            ]);
        }

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_completed_producer',
        ]);

        Mail::assertQueuedCount(2);
        Mail::assertQueued(MissionCompletedMail::class, 2);
    }

    public function test_auto_validate_pending_as_present_is_no_op_when_no_pending_entries(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Present,
            'escrow_status' => EscrowStatus::Released,
        ]);

        $walletTransactionsBefore = \App\Models\WalletTransaction::count();

        $result = $this->service->autoValidatePendingAsPresent($mission);

        // tryCompleteIfReady fires and flips mission to Completed since no Locked+Pending/Absent left.
        $this->assertSame(MissionStatus::Completed, $result->status);
        $this->assertDatabaseMissing('financial_events', [
            'idempotency_key' => "mission_attendance_escrow_release:{$faces[0]['entry']->id}",
        ]);
        $this->assertSame($walletTransactionsBefore, \App\Models\WalletTransaction::count());
        Mail::assertNothingQueued();
    }

    public function test_auto_validate_pending_as_present_rejects_mission_in_closed_status(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1, MissionStatus::Closed);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La validation automatique n\'est possible que sur une mission en attente de validation des présences.');

        $this->service->autoValidatePendingAsPresent($mission);
    }

    public function test_auto_validate_pending_as_present_rejects_mission_in_completed_status(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $mission->update(['status' => MissionStatus::Completed]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La validation automatique n\'est possible que sur une mission en attente de validation des présences.');

        $this->service->autoValidatePendingAsPresent($mission->refresh());
    }

    public function test_auto_validate_pending_as_present_keeps_disputed_entries_locked_but_completes_mission(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $faces[1]['entry']->update([
            'attendance_status' => AttendanceStatus::Disputed,
            'notified_at' => now()->subHours(10),
        ]);

        $result = $this->service->autoValidatePendingAsPresent($mission);

        $this->assertSame(MissionStatus::Completed, $result->status);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => AttendanceStatus::Present->value,
            'escrow_status' => EscrowStatus::Released->value,
        ]);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[1]['entry']->id,
            'attendance_status' => AttendanceStatus::Disputed->value,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_auto_validate_pending_as_present_keeps_mission_pending_when_absent_in_window_remains(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $faces[1]['entry']->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now()->subHours(10),
        ]);

        $result = $this->service->autoValidatePendingAsPresent($mission);

        $this->assertSame(MissionStatus::PendingAttendanceValidation, $result->status);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'attendance_status' => AttendanceStatus::Present->value,
            'escrow_status' => EscrowStatus::Released->value,
        ]);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[1]['entry']->id,
            'attendance_status' => AttendanceStatus::Absent->value,
            'escrow_status' => EscrowStatus::Locked->value,
        ]);
    }

    public function test_auto_validate_pending_as_present_uses_distinct_financial_event_reason(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $this->service->autoValidatePendingAsPresent($mission);

        $event = FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$faces[0]['entry']->id}")->first();
        $this->assertNotNull($event);
        $this->assertSame('attendance_auto_validated', $event->metadata['reason'] ?? null);
    }

    public function test_auto_validate_pending_as_present_rejects_mission_without_paid_payment(): void
    {
        [$mission] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);
        $mission->payment->update(['status' => MissionPaymentStatus::Pending]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La validation des présences requiert un paiement confirmé sur la mission.');

        $this->service->autoValidatePendingAsPresent($mission->refresh());
    }

    // ============================================================
    // FIX-26.6 — Service::autoSettleAbsentAfterDisputeWindow
    // ============================================================

    public function test_auto_settle_absent_after_dispute_window_refunds_producer_and_completes_mission(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $entry = $faces[0]['entry'];
        $entry->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now()->subHours(73),
        ]);

        $producerBalanceBefore = $this->producerUser->refresh()->balance;

        $result = $this->service->autoSettleAbsentAfterDisputeWindow($entry->refresh());

        $this->assertSame(EscrowStatus::Refunded, $result->escrow_status);
        $this->assertSame(AttendanceStatus::Absent, $result->attendance_status);
        $this->assertNotNull($result->refunded_at);

        $this->producerUser->refresh();
        $this->assertSame($producerBalanceBefore + 90000, $this->producerUser->balance);

        $this->assertDatabaseHas('financial_events', [
            'type' => FinancialEventType::Refund->value,
            'idempotency_key' => "mission_attendance_refund:{$entry->id}",
        ]);

        $event = FinancialEvent::where('idempotency_key', "mission_attendance_refund:{$entry->id}")->first();
        $this->assertNotNull($event);
        $this->assertSame('attendance_auto_settled_absent', $event->metadata['reason'] ?? null);
        $this->assertSame(100, $event->metadata['refund_percentage'] ?? null);

        $mission->refresh();
        $this->assertSame(MissionStatus::Completed, $mission->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_completed_producer',
        ]);
    }

    public function test_auto_settle_absent_after_dispute_window_rejects_entry_within_window(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $entry = $faces[0]['entry'];
        $entry->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now()->subHours(71),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La fenêtre de contestation 72h n\'est pas encore expirée pour cette entry.');

        $this->service->autoSettleAbsentAfterDisputeWindow($entry->refresh());
    }

    public function test_auto_settle_absent_after_dispute_window_rejects_entry_with_null_notified_at(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $entry = $faces[0]['entry'];
        $entry->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => null,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La fenêtre de contestation 72h ne peut pas être calculée — `notified_at` manquant.');

        $this->service->autoSettleAbsentAfterDisputeWindow($entry->refresh());
    }

    public function test_auto_settle_absent_after_dispute_window_rejects_pending_entry(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cette entry n\'est pas en absence non contestée — settle impossible.');

        $this->service->autoSettleAbsentAfterDisputeWindow($faces[0]['entry']);
    }

    public function test_auto_settle_absent_after_dispute_window_rejects_disputed_entry(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $entry = $faces[0]['entry'];
        $entry->update([
            'attendance_status' => AttendanceStatus::Disputed,
            'notified_at' => now()->subHours(73),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cette entry n\'est pas en absence non contestée — settle impossible.');

        $this->service->autoSettleAbsentAfterDisputeWindow($entry->refresh());
    }

    public function test_auto_settle_absent_after_dispute_window_rejects_already_refunded_entry(): void
    {
        [, $faces] = $this->createPaidMissionWithFaces(1, MissionStatus::PendingAttendanceValidation);

        $entry = $faces[0]['entry'];
        $entry->update([
            'attendance_status' => AttendanceStatus::Absent,
            'escrow_status' => EscrowStatus::Refunded,
            'notified_at' => now()->subHours(73),
            'refunded_at' => now()->subHour(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cette entry n\'est pas en absence non contestée — settle impossible.');

        $this->service->autoSettleAbsentAfterDisputeWindow($entry->refresh());
    }

    public function test_auto_settle_absent_after_dispute_window_keeps_mission_pending_when_disputed_remains(): void
    {
        [$mission, $faces] = $this->createPaidMissionWithFaces(2, MissionStatus::PendingAttendanceValidation);

        $faces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Absent,
            'notified_at' => now()->subHours(73),
        ]);

        $faces[1]['entry']->update([
            'attendance_status' => AttendanceStatus::Disputed,
            'notified_at' => now()->subHours(50),
        ]);

        $this->service->autoSettleAbsentAfterDisputeWindow($faces[0]['entry']->refresh());

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[0]['entry']->id,
            'escrow_status' => EscrowStatus::Refunded->value,
        ]);

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $faces[1]['entry']->id,
            'escrow_status' => EscrowStatus::Locked->value,
            'attendance_status' => AttendanceStatus::Disputed->value,
        ]);

        $mission->refresh();
        $this->assertSame(MissionStatus::Completed, $mission->status);
    }
}
