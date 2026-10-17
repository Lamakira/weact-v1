<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Mail\FaceSelectedMail;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FedapayService;
use App\Services\MissionAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Lot B4 (décisions D1, D2) — place libérée par une Face qui n'a pas confirmé : le Producer
 * choisit une remplaçante parmi les candidatures non retenues (même argent, net recalculé au
 * palier de la nouvelle Face, aucun débit), la remplaçante a 48 h pour confirmer ; sans
 * remplaçante confirmée au début du jour de tournage, budget_par_face est remboursé au wallet.
 */
class FreedPlaceReplacementTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Lundi 19/10/2026 12:00 UTC ; tournage le 30/10.
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'UTC'));

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
            'balance' => 0,
        ]);
    }

    /**
     * Mission cash payée et clôturée : $selected Faces retenues (accepted, entry locked, acceptées il y a
     * $acceptedHoursAgo h) et $rejected candidatures non retenues (Face Starter).
     *
     * @return array{0: Mission, 1: MissionPayment, 2: list<array{candidature: Candidature, entry: MissionPaymentCandidature}>, 3: list<Candidature>}
     */
    private function paidMission(int $selected = 1, int $rejected = 1, float $acceptedHoursAgo = 49, string $dateTournage = '2026-10-30'): array
    {
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Spot TV jus de bissap',
            'type_mission' => MissionType::Publicite,
            'date_limite_candidature' => '2026-10-15',
            'date_tournage' => $dateTournage,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $selected,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $selected,
            'commission_producteur' => 10000 * $selected,
            'montant_total_producteur' => 110000 * $selected,
            'commission_faces_total' => 10000 * $selected,
            'montant_total_faces' => 90000 * $selected,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subHours((int) $acceptedHoursAgo),
        ]);

        $faces = [];
        for ($i = 0; $i < $selected; $i++) {
            $candidature = $this->candidatureFor($mission, CandidatureStatus::Accepted);
            $candidature->update(['accepted_at' => now()->subMinutes((int) round($acceptedHoursAgo * 60))]);
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $candidature->face_id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'locked_at' => now()->subHours((int) $acceptedHoursAgo),
            ]);
            $faces[] = ['candidature' => $candidature, 'entry' => $entry];
        }

        $rejectedCandidatures = [];
        for ($i = 0; $i < $rejected; $i++) {
            $rejectedCandidatures[] = $this->candidatureFor($mission, CandidatureStatus::Rejected);
        }

        return [$mission, $payment, $faces, $rejectedCandidatures];
    }

    private function candidatureFor(Mission $mission, CandidatureStatus $status, string $tier = 'starter'): Candidature
    {
        $face = Face::factory()->create();
        if ($tier !== 'free') {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
        }
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => "face{$face->id}@test.bj",
        ]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);
    }

    private function replace(Mission $mission, Candidature $candidature): TestResponse
    {
        return $this->actingAs($this->producerUser)->postJson(
            "/api/v1/producer/missions/{$mission->uuid}/freed-places/replace",
            ['candidature_id' => $candidature->uuid],
        );
    }

    private function faceUserId(Candidature $candidature): int
    {
        return (int) User::query()->where('userable_type', Face::class)->where('userable_id', $candidature->face_id)->value('id');
    }

    // --- 1. Libération de la place ---

    public function test_an_unconfirmed_face_frees_her_place_without_refund_when_a_replacement_is_possible(): void
    {
        [$mission, , $faces] = $this->paidMission();

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);
        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertNotNull($entry->freed_at);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);

        $message = Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'mission_place_freed')->sole()->data['message'];
        $this->assertStringContainsString('sa place est libérée', $message);
        $this->assertStringContainsString('Choisissez une remplaçante parmi les candidatures non retenues avant le 30/10 à 00:00', $message);
        $this->assertStringContainsString('100 000 FCFA', $message);
    }

    public function test_the_place_is_refunded_at_once_when_no_rejected_candidature_can_replace(): void
    {
        [, , $faces] = $this->paidMission(rejected: 0);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertNull($entry->freed_at);
        $this->assertSame(100000, (int) $this->producerUser->fresh()->balance);
    }

    public function test_the_place_is_refunded_at_once_when_the_shooting_day_has_started(): void
    {
        // Sélection payée la veille au soir : plancher B3 de 12 h, expiration pendant le jour de tournage.
        [, , $faces] = $this->paidMission(acceptedHoursAgo: 13, dateTournage: '2026-10-19');

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(EscrowStatus::Refunded, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(100000, (int) $this->producerUser->fresh()->balance);
    }

    // --- 2. Remplacement ---

    public function test_the_producer_replaces_the_freed_place_with_a_rejected_candidature_at_no_extra_cost(): void
    {
        [$mission, $payment, $faces] = $this->paidMission(rejected: 0);
        $replacement = $this->candidatureFor($mission, CandidatureStatus::Rejected, 'elite');
        $this->artisan('missions:expire-unconfirmed-faces');

        $this->replace($mission, $replacement)->assertOk();

        $replacement->refresh();
        $this->assertSame(CandidatureStatus::Accepted, $replacement->status);
        $this->assertTrue($replacement->accepted_at->equalTo(now()));

        $old = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Transferred, $old->escrow_status);

        $new = MissionPaymentCandidature::query()->where('candidature_id', $replacement->id)->sole();
        $this->assertSame($payment->id, $new->mission_payment_id);
        $this->assertSame(EscrowStatus::Locked, $new->escrow_status);
        $this->assertSame(AttendanceStatus::Pending, $new->attendance_status);
        $this->assertSame($old->id, $new->replacement_of_entry_id);
        // Palier Élite : commission Face 5 % de budget_par_face.
        $this->assertSame(95000, (int) $new->montant_face_recoit);

        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
        $this->assertTrue(Conversation::query()->where('candidature_id', $replacement->id)->exists());

        $notification = Notification::query()
            ->where('user_id', $this->faceUserId($replacement))
            ->where('type', 'mission_place_freed_selected')
            ->sole();
        $this->assertStringContainsString("Une place s'est libérée", $notification->data['message']);
        // Échéance B3 : acceptation + 48 h = 21/10 12:00 UTC = 13:00 à Porto-Novo.
        $this->assertStringContainsString('Confirmez votre participation avant le 21/10 à 13:00', $notification->data['message']);
        Mail::assertQueued(FaceSelectedMail::class, fn (FaceSelectedMail $mail): bool => $mail->hasTo("face{$replacement->face_id}@test.bj")
            && $mail->amount === 95000
            && $mail->confirmationDeadline !== null);
    }

    public function test_replacement_guards(): void
    {
        [$mission, , , $rejected] = $this->paidMission(rejected: 1);

        // Aucune place libérée.
        $this->replace($mission, $rejected[0])->assertStatus(422)->assertJsonValidationErrors(['place']);

        $this->artisan('missions:expire-unconfirmed-faces');

        $pending = $this->candidatureFor($mission, CandidatureStatus::Pending);
        $this->replace($mission, $pending)->assertStatus(422)->assertJsonValidationErrors(['candidature_id']);

        $free = $this->candidatureFor($mission, CandidatureStatus::Rejected, 'free');
        $this->replace($mission, $free)->assertStatus(422)->assertJsonValidationErrors(['candidature_id']);

        $otherMission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $foreign = $this->candidatureFor($otherMission, CandidatureStatus::Rejected);
        $this->replace($mission, $foreign)->assertStatus(422)->assertJsonValidationErrors(['candidature_id']);

        $otherProducer = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);
        $this->actingAs($otherProducer)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/freed-places/replace", ['candidature_id' => $rejected[0]->uuid])
            ->assertForbidden();

        // Jour du tournage commencé (30/10 00:00 à Porto-Novo).
        $this->travelTo(Carbon::parse('2026-10-29 23:00:00', 'UTC'));
        $this->replace($mission, $rejected[0])->assertStatus(422)->assertJsonValidationErrors(['place']);

        $this->assertSame(CandidatureStatus::Rejected, $rejected[0]->fresh()->status);
    }

    public function test_a_replacement_who_confirms_is_paid_her_own_net_at_attendance(): void
    {
        [$mission, , , $rejected] = $this->paidMission();
        $this->artisan('missions:expire-unconfirmed-faces');
        $this->replace($mission, $rejected[0])->assertOk();

        $this->actingAs(User::query()->findOrFail($this->faceUserId($rejected[0])))
            ->postJson("/api/v1/face/candidatures/{$rejected[0]->uuid}/confirm")
            ->assertOk();
        $this->assertContains($rejected[0]->fresh()->status, [CandidatureStatus::Confirmed, CandidatureStatus::InProgress]);

        $this->travelTo(Carbon::parse('2026-10-30 08:00:00', 'UTC'));
        $entry = MissionPaymentCandidature::query()->where('candidature_id', $rejected[0]->id)->sole();
        app(MissionAttendanceService::class)->markAttendance($mission, [$entry->id => 'present'], $this->producerUser);

        $this->assertSame(EscrowStatus::Released, $entry->fresh()->escrow_status);
        $this->assertSame(90000, (int) User::query()->findOrFail($this->faceUserId($rejected[0]))->balance);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
    }

    public function test_a_replacement_who_does_not_confirm_frees_the_place_again(): void
    {
        [$mission, , , $rejected] = $this->paidMission(rejected: 2);
        $this->artisan('missions:expire-unconfirmed-faces');
        $this->replace($mission, $rejected[0])->assertOk();

        $this->travelTo(now()->addHours(49));
        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        $this->assertSame(CandidatureStatus::Expired, $rejected[0]->fresh()->status);
        $entry = MissionPaymentCandidature::query()->where('candidature_id', $rejected[0]->id)->sole();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertNotNull($entry->freed_at);

        $this->replace($mission, $rejected[1])->assertOk();
        $this->assertSame(EscrowStatus::Transferred, $entry->fresh()->escrow_status);
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);
    }

    // --- 3. Remboursement au début du jour de tournage ---

    public function test_a_freed_place_without_replacement_is_refunded_at_the_start_of_the_shooting_day(): void
    {
        [$mission, , $faces] = $this->paidMission(selected: 2);
        $faces[1]['candidature']->update(['status' => CandidatureStatus::Confirmed]);
        $this->artisan('missions:expire-unconfirmed-faces');

        $this->travelTo(Carbon::parse('2026-10-29 22:59:00', 'UTC'));
        $this->artisan('missions:refund-freed-places')->expectsOutputToContain('Freed places refunded: 0');
        $this->assertSame(0, (int) $this->producerUser->fresh()->balance);

        $this->travelTo(Carbon::parse('2026-10-29 23:00:00', 'UTC'));
        $this->artisan('missions:refund-freed-places')
            ->expectsOutputToContain('Freed places refunded: 1')
            ->assertSuccessful();

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertSame(100000, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(EscrowStatus::Locked, $faces[1]['entry']->fresh()->escrow_status);

        $message = Notification::query()->where('user_id', $this->producerUser->id)->where('type', 'mission_freed_place_refunded')->sole()->data['message'];
        $this->assertStringContainsString('100 000 FCFA ont été crédités sur votre wallet', $message);

        // Idempotent.
        $this->artisan('missions:refund-freed-places')->expectsOutputToContain('Freed places refunded: 0');
        $this->assertSame(100000, (int) $this->producerUser->fresh()->balance);
    }

    public function test_a_freed_place_is_never_paid_to_anyone(): void
    {
        [$mission, , $faces] = $this->paidMission(selected: 2);
        $faces[1]['candidature']->update(['status' => CandidatureStatus::Confirmed]);
        $this->artisan('missions:expire-unconfirmed-faces');

        $this->assertSame(
            ['candidatures', 'Une place libérée attend une remplaçante ou son remboursement : la mission ne peut pas encore être terminée.'],
            $mission->fresh()->completionBlocker(),
        );

        // Validation automatique des présences : la place libérée n'est pas versée.
        $this->travelTo(Carbon::parse('2026-10-30 08:00:00', 'UTC'));
        $mission->update(['status' => MissionStatus::PendingAttendanceValidation]);
        app(MissionAttendanceService::class)->autoValidatePendingAsPresent($mission->fresh());

        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, (int) User::query()->findOrFail($this->faceUserId($faces[0]['candidature']))->balance);
        $this->assertSame(EscrowStatus::Released, $faces[1]['entry']->fresh()->escrow_status);
    }

    // --- 4. Toutes les Faces sélectionnées expirées ---

    public function test_when_every_selected_face_expired_all_places_are_refunded_and_the_mission_cannot_be_completed(): void
    {
        [$mission, , $faces] = $this->paidMission(selected: 2, rejected: 0);

        $this->artisan('missions:expire-unconfirmed-faces')->assertSuccessful();

        foreach ($faces as $face) {
            $this->assertSame(EscrowStatus::Refunded, $face['entry']->fresh()->escrow_status);
        }
        $this->assertSame(200000, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        $this->assertSame(
            ['candidatures', "Aucune Face n'a confirmé sa participation : il n'y a rien à terminer. Les places ont été remboursées sur votre wallet."],
            $mission->fresh()->completionBlocker(),
        );
        $this->assertTrue(Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'mission_all_places_refunded')
            ->exists());
    }

    // --- 5. Affichage et comptabilité ---

    public function test_the_producer_sees_the_freed_place_on_the_mission(): void
    {
        [$mission] = $this->paidMission();
        $this->artisan('missions:expire-unconfirmed-faces');

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.freed_places_count', 1)
            ->assertJsonPath('data.can_replace_freed_place', true)
            ->assertJsonPath('data.freed_places_refund_at', '2026-10-29T23:00:00+00:00');
    }

    public function test_admin_revenue_follows_the_commission_of_the_replacement(): void
    {
        [$mission] = $this->paidMission(rejected: 0);
        $replacement = $this->candidatureFor($mission, CandidatureStatus::Rejected, 'elite');
        $this->artisan('missions:expire-unconfirmed-faces');
        $this->replace($mission, $replacement)->assertOk();

        $admin = Admin::factory()->create();
        $this->mock(FedapayService::class, fn ($mock) => $mock->shouldReceive('getBalanceSummary')->andReturn([
            'available' => false,
            'total_amount' => 0,
            'refreshed_at' => null,
            'error' => null,
        ]));
        // Commission Producer 10 000 + commission Face de la remplaçante (Élite 5 %) 5 000.
        $this->withToken($admin->createToken('admin-token', ['2fa'])->plainTextToken)
            ->getJson('/api/v1/admin/finance/overview')
            ->assertOk()
            ->assertJsonPath('data.platform.from_missions', 15000);
    }
}
