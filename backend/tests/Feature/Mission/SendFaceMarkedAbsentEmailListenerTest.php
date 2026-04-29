<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Events\MissionAttendanceMarkedAbsent;
use App\Listeners\Mission\SendFaceMarkedAbsentEmail;
use App\Mail\FaceMarkedAbsentMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendFaceMarkedAbsentEmailListenerTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Mission $mission;

    private Face $face;

    private User $faceUser;

    private MissionPaymentCandidature $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->individual()->create([
            'first_name' => 'Studio',
            'last_name' => 'Beta',
        ]);
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Pub TV Été 2026',
            'date_tournage' => '2026-04-29',
        ]);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        $this->mission = $mission->fresh();

        $payment = MissionPayment::create([
            'mission_id' => $this->mission->id,
            'producer_id' => $this->producer->id,
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

        $this->face = Face::factory()->create(['prenom' => 'Amina']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
            'email' => 'amina@example.test',
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $this->entry = MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $this->face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'attendance_status' => AttendanceStatus::Absent,
            'locked_at' => now(),
            'notified_at' => now(),
        ]);
    }

    public function test_listener_queues_face_marked_absent_mail(): void
    {
        Mail::fake();

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(
            FaceMarkedAbsentMail::class,
            fn (FaceMarkedAbsentMail $mail): bool => $mail->hasTo('amina@example.test')
                && $mail->amount === 90_000
                && $mail->mission->id === $this->mission->id
                && $mail->face->id === $this->face->id
                && $mail->producer->id === $this->producer->id,
        );
    }

    public function test_listener_skips_when_face_user_is_missing(): void
    {
        Mail::fake();

        $this->faceUser->delete();

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_face_email_is_empty(): void
    {
        Mail::fake();

        $this->faceUser->update(['email' => '']);

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry));

        Mail::assertNothingQueued();
    }

    public function test_listener_skips_when_notified_at_is_null(): void
    {
        Mail::fake();

        $this->entry->update(['notified_at' => null]);

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry->fresh()));

        Mail::assertNothingQueued();
    }

    public function test_listener_does_not_throw_when_mailer_fails(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceMarkedAbsentMail queue failed'
                && ($context['entry_id'] ?? null) === $this->entry->id);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Mailer down'));

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry));
    }

    public function test_listener_does_not_throw_when_queue_serialization_fails(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'FaceMarkedAbsentMail queue failed'
                && ($context['entry_id'] ?? null) === $this->entry->id);

        $pendingMail = \Mockery::mock();
        $pendingMail->shouldReceive('queue')->andThrow(new \RuntimeException('Queue connection down'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        (new SendFaceMarkedAbsentEmail)->handle(new MissionAttendanceMarkedAbsent($this->entry));
    }
}
