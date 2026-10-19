<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Mail\FaceConfirmationReminderMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindUnconfirmedFacesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /** @return array{0: Candidature, 1: User, 2: Mission} */
    private function acceptedFace(float $acceptedHoursAgo): array
    {
        $producer = Producer::factory()->create();
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->addDays(20)->format('Y-m-d'),
        ]);
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subMinutes((int) round($acceptedHoursAgo * 60)),
        ]);
        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
            'accepted_at' => now()->subMinutes((int) round($acceptedHoursAgo * 60)),
        ]);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
        ]);

        return [$candidature, $faceUser, $mission];
    }

    public function test_reminder_is_sent_once_when_24h_or_less_remain(): void
    {
        [$candidature, $faceUser, $mission] = $this->acceptedFace(25);

        $this->artisan('missions:remind-unconfirmed-faces')->assertSuccessful();

        $notification = Notification::where('user_id', $faceUser->id)->where('type', 'mission_confirmation_reminder')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString("Confirmez votre participation à « {$mission->titre} » avant le ", $notification->data['message']);
        $this->assertStringContainsString('sinon votre place sera libérée.', $notification->data['message']);
        $this->assertSame('/face/candidatures', $notification->data['url']);
        Mail::assertQueued(FaceConfirmationReminderMail::class, fn (FaceConfirmationReminderMail $m): bool => $m->hasTo($faceUser->email));
        $this->assertNotNull($candidature->fresh()->confirmation_reminder_sent_at);

        $this->artisan('missions:remind-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'mission_confirmation_reminder')->count());
        Mail::assertQueuedCount(1);
    }

    public function test_no_reminder_when_more_than_24h_remain(): void
    {
        [$candidature] = $this->acceptedFace(10);

        $this->artisan('missions:remind-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'mission_confirmation_reminder')->count());
        $this->assertNull($candidature->fresh()->confirmation_reminder_sent_at);
        Mail::assertNothingQueued();
    }

    public function test_no_reminder_after_the_deadline(): void
    {
        [$candidature] = $this->acceptedFace(49);

        $this->artisan('missions:remind-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'mission_confirmation_reminder')->count());
        $this->assertNull($candidature->fresh()->confirmation_reminder_sent_at);
    }

    public function test_no_reminder_once_the_face_confirmed(): void
    {
        [$candidature] = $this->acceptedFace(30);
        $candidature->update(['status' => CandidatureStatus::Confirmed]);

        $this->artisan('missions:remind-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'mission_confirmation_reminder')->count());
    }
}
