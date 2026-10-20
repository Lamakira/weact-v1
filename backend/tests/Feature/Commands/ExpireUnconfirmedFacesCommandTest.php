<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\FaceConfirmationExpiredMail;
use App\Mail\MissionFaceUnconfirmedMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExpireUnconfirmedFacesCommandTest extends TestCase
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
     * Mission cash payée et clôturée, $faceCount Faces sélectionnées (Accepted, entry Locked).
     *
     * @return array{0: Mission, 1: MissionPayment, 2: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User}>}
     */
    private function paidSelection(int $faceCount, float $acceptedHoursAgo, ?string $dateTournage = null): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'type_mission' => MissionType::Autre,
            'type_mission_autre' => 'Shooting',
            'date_tournage' => $dateTournage ?? now()->addDays(10)->format('Y-m-d'),
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
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subHours((int) $acceptedHoursAgo),
        ]);

        $faces = [];

        for ($i = 0; $i < $faceCount; $i++) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Accepted,
                'accepted_at' => now()->subMinutes((int) round($acceptedHoursAgo * 60)),
            ]);
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now()->subHours((int) $acceptedHoursAgo),
            ]);

            $faces[] = compact('candidature', 'entry', 'faceUser');
        }

        return [$mission, $payment, $faces];
    }

    public function test_face_accepted_49h_ago_is_expired_and_producer_refunded_the_place_budget(): void
    {
        [$mission, , $faces] = $this->paidSelection(1, 49);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertNotNull($entry->refunded_at);

        // budget_par_face (100 000), pas le net Face (90 000).
        $this->assertSame(100000, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'type' => 'credit',
            'amount' => 100000,
            'description' => "Mission : place non confirmée — {$mission->titre}",
        ]);

        $event = FinancialEvent::query()->where('type', 'refund')->first();
        $this->assertNotNull($event);
        $this->assertSame(100000, (int) $event->amount);

        $faceNotification = Notification::where('user_id', $faces[0]['faceUser']->id)->where('type', 'mission_confirmation_expired')->first();
        $this->assertNotNull($faceNotification);
        $this->assertSame("Vous n'avez pas confirmé votre participation à « {$mission->titre} » dans les délais : votre place a été libérée.", $faceNotification->data['message']);

        $producerNotification = Notification::where('user_id', $this->producerUser->id)->where('type', 'mission_face_confirmation_expired')->first();
        $this->assertNotNull($producerNotification);
        $this->assertStringContainsString('100 000 FCFA ont été crédités sur votre wallet', $producerNotification->data['message']);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $producerNotification->data['url']);

        Mail::assertQueued(FaceConfirmationExpiredMail::class, fn (FaceConfirmationExpiredMail $m): bool => $m->hasTo($faces[0]['faceUser']->email));
        Mail::assertQueued(MissionFaceUnconfirmedMail::class, fn (MissionFaceUnconfirmedMail $m): bool => $m->hasTo($this->producerUser->email));
    }

    public function test_face_accepted_47h_ago_is_untouched(): void
    {
        [, , $faces] = $this->paidSelection(1, 47);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        Mail::assertNothingQueued();
    }

    public function test_deadline_is_the_start_of_the_shooting_day_when_it_comes_before_48h(): void
    {
        $tomorrow = now((string) config('app.business_timezone'))->addDay()->format('Y-m-d');
        [, , $faces] = $this->paidSelection(1, 13, $tomorrow);
        $today = now((string) config('app.business_timezone'))->format('Y-m-d');
        [, , $notYet] = $this->paidSelection(1, 10, now((string) config('app.business_timezone'))->addDays(3)->format('Y-m-d'));

        // Tournage demain : l'échéance (début de demain) est encore dans le futur.
        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();
        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);

        // Le jour de tournage est atteint avant les 48 h : expirée.
        $faces[0]['candidature']->mission->update(['date_tournage' => $today]);
        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Refunded, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(CandidatureStatus::Accepted, $notYet[0]['candidature']->fresh()->status);
    }

    public function test_selection_paid_on_the_shooting_day_still_leaves_twelve_hours_to_confirm(): void
    {
        $today = now((string) config('app.business_timezone'))->format('Y-m-d');
        [, , $faces] = $this->paidSelection(1, 1, $today);
        $candidature = $faces[0]['candidature']->fresh(['mission']);

        $this->assertEqualsWithDelta(
            $candidature->accepted_at->copy()->addHours(12)->getTimestamp(),
            $candidature->confirmationDeadline()?->getTimestamp(),
            1,
        );

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();
        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
    }

    public function test_face_already_marked_absent_is_not_expired_nor_refunded_twice(): void
    {
        // L'absence (72 h de contestation puis remboursement de la part nette) prime : le sweep
        // ne doit ni passer la candidature en expired ni rembourser la place une seconde fois.
        [$mission, , $faces] = $this->paidSelection(1, 49);
        $faces[0]['entry']->update(['attendance_status' => AttendanceStatus::Absent]);
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
    }

    public function test_second_run_is_idempotent_and_does_not_credit_twice(): void
    {
        [, , $faces] = $this->paidSelection(1, 49);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();
        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(100000, $this->producerUser->fresh()->balance);
        $this->assertSame(1, Notification::where('type', 'mission_face_confirmation_expired')->count());
        $this->assertSame(1, FinancialEvent::query()->where('type', 'refund')->count());
        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);
    }

    public function test_confirmed_face_moves_in_progress_and_mission_completes_paying_only_her(): void
    {
        [$mission, , $faces] = $this->paidSelection(2, 49);
        $faces[0]['candidature']->update(['status' => CandidatureStatus::Confirmed]);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::InProgress, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(CandidatureStatus::Expired, $faces[1]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(EscrowStatus::Refunded, $faces[1]['entry']->fresh()->escrow_status);

        $completed = app(MissionService::class)->completeMission($mission->fresh());

        $this->assertSame(MissionStatus::Completed, $completed->status);
        $this->assertSame(EscrowStatus::Released, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(AttendanceStatus::Present, $faces[0]['entry']->fresh()->attendance_status);
        $this->assertSame(90000, $faces[0]['faceUser']->fresh()->balance);
        $this->assertSame(0, $faces[1]['faceUser']->fresh()->balance);
        $this->assertSame(CandidatureStatus::Completed, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(CandidatureStatus::Expired, $faces[1]['candidature']->fresh()->status);
        // Le Producteur n'a reçu que le remboursement de la place expirée.
        $this->assertSame(100000, $this->producerUser->fresh()->balance);
    }

    public function test_attendance_listing_excludes_expired_entries_and_validation_rejects_them(): void
    {
        [$mission, , $faces] = $this->paidSelection(2, 49);
        $faces[0]['candidature']->update(['status' => CandidatureStatus::Confirmed]);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertOk()
            ->assertJsonCount(1, 'data.entries');

        // Lot B5 : jour du tournage atteint, sinon la garde de date répond avant celle des entries.
        $mission->update(['date_tournage' => now()->toDateString()]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/validate-attendance", [
                'entries' => [['entry_id' => $faces[1]['entry']->id, 'status' => 'present']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entries');
    }

    public function test_ugc_accepted_candidature_is_untouched(): void
    {
        [$mission, , $faces] = $this->paidSelection(1, 100);
        $mission->update(['type_mission' => MissionType::Ugc]);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Accepted, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
    }

    public function test_admin_platform_revenue_excludes_the_face_commission_refunded_with_an_expired_place(): void
    {
        // D1 : la place expirée est remboursée sur budget_par_face (net Face + commission Face) ;
        // seule la commission Producteur reste un revenu WeAct pour cette place.
        [, , $faces] = $this->paidSelection(2, 49);
        $faces[1]['candidature']->update(['status' => CandidatureStatus::Confirmed]);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();
        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);

        $fedapay = \Mockery::mock(\App\Services\FedapayService::class);
        $fedapay->shouldReceive('getBalanceSummary')->andReturn([
            'available' => true,
            'total_amount' => 0,
            'refreshed_at' => now()->toIso8601String(),
            'error' => null,
        ]);
        $this->app->instance(\App\Services\FedapayService::class, $fedapay);
        $admin = \App\Models\Admin::factory()->create();

        // Encaissé 220 000 ; rendu 100 000 au Producteur ; 90 000 en escrow pour la Face confirmée :
        // reste 30 000 de commissions (2 × 10 000 Producteur + 10 000 Face confirmée).
        $this->withToken($admin->createToken('admin-token', ['2fa'])->plainTextToken)
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.platform.from_missions', 30000)
            ->assertJsonPath('data.faces.escrow_mission', 90000);
    }
}
