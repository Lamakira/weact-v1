<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Events\MissionAttendanceMarkedAbsent;
use App\Listeners\Mission\NotifyFaceOnAttendanceAbsence;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifyFaceOnAttendanceAbsenceListenerTest extends TestCase
{
    use RefreshDatabase;

    private MissionPaymentCandidature $entry;

    private User $faceUser;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->individual()->create([
            'first_name' => 'Studio',
            'last_name' => 'Beta',
        ]);
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'titre' => 'Pub TV Été 2026',
        ]);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        $this->mission = $mission->fresh();

        $payment = MissionPayment::create([
            'mission_id' => $this->mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $face = Face::factory()->create(['prenom' => 'Amina']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'attendance_status' => AttendanceStatus::Absent,
            'locked_at' => now(),
            'notified_at' => now(),
        ]);
    }

    public function test_listener_creates_in_app_notification_with_correct_payload(): void
    {
        (new NotifyFaceOnAttendanceAbsence)->handle(new MissionAttendanceMarkedAbsent($this->entry));

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'mission_attendance_absent')
            ->firstOrFail();

        $this->assertSame($this->mission->id, $notification->data['mission_id']);
        $this->assertSame($this->entry->id, $notification->data['entry_id']);
        $this->assertSame("/face/missions/{$this->mission->uuid}", $notification->data['url']);
        $this->assertStringContainsString('Studio Beta', $notification->data['message']);
        $this->assertStringContainsString('Pub TV Été 2026', $notification->data['message']);
        $this->assertStringContainsString('72h pour contester', $notification->data['message']);

        $expectedDeadline = $this->entry->fresh()->notified_at->copy()->addHours(72)->toIso8601String();
        $this->assertSame($expectedDeadline, $notification->data['dispute_deadline']);
        $this->assertStringContainsString("«\u{a0}{$this->mission->titre}\u{a0}»", $notification->data['message']);
    }

    public function test_listener_skips_when_face_user_is_missing(): void
    {
        $this->faceUser->delete();

        (new NotifyFaceOnAttendanceAbsence)->handle(new MissionAttendanceMarkedAbsent($this->entry));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'mission_attendance_absent',
        ]);
    }

    public function test_listener_skips_when_notified_at_is_null(): void
    {
        $this->entry->update(['notified_at' => null]);

        (new NotifyFaceOnAttendanceAbsence)->handle(new MissionAttendanceMarkedAbsent($this->entry->fresh()));

        $this->assertDatabaseMissing('notifications', [
            'type' => 'mission_attendance_absent',
        ]);
    }
}
