<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
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

class ReconcileStaleSelectionsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{mission: Mission, payment: MissionPayment, producerUser: User, accepted: Candidature, acceptedFaceUser: User, rejected: list<Candidature>, rejectedFaceUsers: list<User>, cancelled: list<Candidature>}> */
    private array $scenarios = [];

    private const REJECTED_PER_MISSION = 3;

    private const CANCELLED_PER_MISSION = 1;

    /**
     * Seed the four real-world scenarios in scaled-down form.
     *
     * Real-world: missions 8, 11, 14, 17 with 1 accepted + 114/27/19/17 rejected
     * + 4/2/0/0 cancelled. Test uses the same shape (1 accepted + N rejected
     * + M cancelled per mission) at lower counts so assertions stay fast.
     */
    private function seedFourScenarios(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $producer = Producer::factory()->create();
            $producerUser = User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);

            $mission = Mission::factory()->create([
                'producer_id' => $producer->id,
                'status' => MissionStatus::PendingPayment,
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
                'fedapay_transaction_id' => null,
                'status' => MissionPaymentStatus::Pending,
            ]);

            // Accepted candidature with mpc entry (the "selected" face)
            $acceptedFace = Face::factory()->create();
            $acceptedFaceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $acceptedFace->id,
            ]);
            $acceptedCandidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $acceptedFace->id,
                'status' => CandidatureStatus::Accepted,
            ]);
            MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $acceptedCandidature->id,
                'face_id' => $acceptedFace->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Pending,
            ]);

            // Rejected candidatures (mass-rejected, no mpc entry — Requête 2 scope)
            $rejected = [];
            $rejectedFaceUsers = [];
            for ($r = 0; $r < self::REJECTED_PER_MISSION; $r++) {
                $face = Face::factory()->create();
                $rejectedFaceUsers[] = User::factory()->create([
                    'userable_type' => Face::class,
                    'userable_id' => $face->id,
                ]);
                $rejected[] = Candidature::factory()->create([
                    'mission_id' => $mission->id,
                    'face_id' => $face->id,
                    'status' => CandidatureStatus::Rejected,
                ]);
            }

            // Cancelled candidatures (must NEVER be touched)
            $cancelled = [];
            for ($c = 0; $c < self::CANCELLED_PER_MISSION; $c++) {
                $face = Face::factory()->create();
                User::factory()->create([
                    'userable_type' => Face::class,
                    'userable_id' => $face->id,
                ]);
                $cancelled[] = Candidature::factory()->create([
                    'mission_id' => $mission->id,
                    'face_id' => $face->id,
                    'status' => CandidatureStatus::Cancelled,
                ]);
            }

            $this->scenarios[] = [
                'mission' => $mission,
                'payment' => $payment,
                'producerUser' => $producerUser,
                'accepted' => $acceptedCandidature,
                'acceptedFaceUser' => $acceptedFaceUser,
                'rejected' => $rejected,
                'rejectedFaceUsers' => $rejectedFaceUsers,
                'cancelled' => $cancelled,
            ];
        }
    }

    public function test_signature_requires_exactly_one_of_dry_run_or_apply(): void
    {
        $this->seedFourScenarios();

        $this->artisan('candidature:reconcile-stale-selections')
            ->expectsOutputToContain('Specify exactly one of --dry-run or --apply.')
            ->assertExitCode(1);

        $this->artisan('candidature:reconcile-stale-selections', [
            '--dry-run' => true,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Specify exactly one of --dry-run or --apply.')
            ->assertExitCode(1);
    }

    public function test_dry_run_leaves_every_row_unchanged_and_queues_no_notifications(): void
    {
        $this->seedFourScenarios();

        $candidatureSnapshot = Candidature::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $paymentSnapshot = MissionPayment::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $missionSnapshot = Mission::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $entrySnapshot = MissionPaymentCandidature::query()->orderBy('id')->get(['id', 'mission_payment_id', 'candidature_id'])->toArray();

        $this->artisan('candidature:reconcile-stale-selections', [
            '--dry-run' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        $this->assertEquals(
            $candidatureSnapshot,
            Candidature::query()->orderBy('id')->get(['id', 'status'])->toArray(),
            'Candidatures should be untouched in dry-run.'
        );
        $this->assertEquals(
            $paymentSnapshot,
            MissionPayment::query()->orderBy('id')->get(['id', 'status'])->toArray(),
            'Payments should be untouched in dry-run.'
        );
        $this->assertEquals(
            $missionSnapshot,
            Mission::query()->orderBy('id')->get(['id', 'status'])->toArray(),
            'Missions should be untouched in dry-run.'
        );
        $this->assertEquals(
            $entrySnapshot,
            MissionPaymentCandidature::query()->orderBy('id')->get(['id', 'mission_payment_id', 'candidature_id'])->toArray(),
            'mission_payment_candidatures should be untouched in dry-run.'
        );
        $this->assertSame(0, Notification::query()->count(), 'No notifications should be created in dry-run.');
    }

    public function test_apply_flips_in_scope_candidatures_to_pending_and_preserves_cancelled(): void
    {
        $this->seedFourScenarios();

        $cancelledIds = collect($this->scenarios)
            ->flatMap(fn (array $scenario) => collect($scenario['cancelled'])->pluck('id'))
            ->all();

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        foreach ($this->scenarios as $scenario) {
            $this->assertDatabaseHas('candidatures', [
                'id' => $scenario['accepted']->id,
                'status' => CandidatureStatus::Pending->value,
            ]);

            foreach ($scenario['rejected'] as $rejected) {
                $this->assertDatabaseHas('candidatures', [
                    'id' => $rejected->id,
                    'status' => CandidatureStatus::Pending->value,
                ]);
            }

            $this->assertDatabaseHas('mission_payments', [
                'id' => $scenario['payment']->id,
                'status' => MissionPaymentStatus::Failed->value,
            ]);

            $this->assertDatabaseHas('missions', [
                'id' => $scenario['mission']->id,
                'status' => MissionStatus::Published->value,
            ]);

            $this->assertDatabaseMissing('mission_payment_candidatures', [
                'mission_payment_id' => $scenario['payment']->id,
            ]);
        }

        foreach ($cancelledIds as $cancelledId) {
            $this->assertDatabaseHas('candidatures', [
                'id' => $cancelledId,
                'status' => CandidatureStatus::Cancelled->value,
            ]);
        }
    }

    public function test_apply_writes_the_three_expected_notification_types_with_exact_french_strings(): void
    {
        $this->seedFourScenarios();

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        $expectedAccepted = 4;
        $expectedRejected = 4 * self::REJECTED_PER_MISSION;
        $expectedProducer = 4;

        $this->assertSame(
            $expectedAccepted,
            Notification::query()->where('type', 'candidature_reset_from_accepted')->count(),
            'One candidature_reset_from_accepted notification per accepted Face.'
        );
        $this->assertSame(
            $expectedRejected,
            Notification::query()->where('type', 'candidature_reset_from_rejected')->count(),
            'One candidature_reset_from_rejected notification per rejected Face.'
        );
        $this->assertSame(
            $expectedProducer,
            Notification::query()->where('type', 'mission_selection_reset_producer')->count(),
            'One mission_selection_reset_producer notification per Producer.'
        );

        $this->assertSame(
            $expectedAccepted + $expectedRejected + $expectedProducer,
            Notification::query()->count(),
            'Total notifications must equal accepted + rejected + producer counts (no extras).'
        );

        $sampleScenario = $this->scenarios[0];
        $missionTitre = $sampleScenario['mission']->titre;
        $missionUuid = $sampleScenario['mission']->uuid;

        $acceptedFaceUser = $sampleScenario['acceptedFaceUser'];
        /** @var Notification $acceptedNotif */
        $acceptedNotif = Notification::query()
            ->where('user_id', $acceptedFaceUser->id)
            ->where('type', 'candidature_reset_from_accepted')
            ->firstOrFail();
        $this->assertSame(
            'Le paiement du producteur pour la mission « '.$missionTitre.' » n\'a pas été finalisé. Votre candidature a été remise en attente et pourra à nouveau être sélectionnée si le producteur relance la sélection.',
            $acceptedNotif->data['message']
        );
        $this->assertSame($sampleScenario['mission']->id, $acceptedNotif->data['mission_id']);
        $this->assertSame($missionTitre, $acceptedNotif->data['mission_titre']);
        $this->assertSame('/face/candidatures', $acceptedNotif->data['url']);

        $rejectedFaceUser = $sampleScenario['rejectedFaceUsers'][0];
        /** @var Notification $rejectedNotif */
        $rejectedNotif = Notification::query()
            ->where('user_id', $rejectedFaceUser->id)
            ->where('type', 'candidature_reset_from_rejected')
            ->firstOrFail();
        $this->assertSame(
            'La sélection précédente pour la mission « '.$missionTitre.' » n\'a pas été finalisée. Votre candidature est de nouveau en attente et peut être retenue si le producteur relance la sélection.',
            $rejectedNotif->data['message']
        );
        $this->assertSame('/face/candidatures', $rejectedNotif->data['url']);

        /** @var Notification $producerNotif */
        $producerNotif = Notification::query()
            ->where('user_id', $sampleScenario['producerUser']->id)
            ->where('type', 'mission_selection_reset_producer')
            ->firstOrFail();
        $this->assertSame(
            'Le paiement de votre sélection pour la mission « '.$missionTitre.' » n\'a pas abouti. La mission est de nouveau ouverte aux candidatures — vous pouvez relancer votre sélection depuis la page de la mission.',
            $producerNotif->data['message']
        );
        $this->assertSame('/producer/missions/'.$missionUuid.'/candidatures', $producerNotif->data['url']);
    }

    public function test_second_apply_run_is_a_no_op(): void
    {
        $this->seedFourScenarios();

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        $candidaturesAfterFirst = Candidature::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $paymentsAfterFirst = MissionPayment::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $missionsAfterFirst = Mission::query()->orderBy('id')->get(['id', 'status'])->toArray();
        $notificationsAfterFirst = Notification::query()->count();

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])
            ->expectsOutputToContain('No stale selections to reconcile')
            ->assertSuccessful();

        $this->assertEquals(
            $candidaturesAfterFirst,
            Candidature::query()->orderBy('id')->get(['id', 'status'])->toArray()
        );
        $this->assertEquals(
            $paymentsAfterFirst,
            MissionPayment::query()->orderBy('id')->get(['id', 'status'])->toArray()
        );
        $this->assertEquals(
            $missionsAfterFirst,
            Mission::query()->orderBy('id')->get(['id', 'status'])->toArray()
        );
        $this->assertSame(
            $notificationsAfterFirst,
            Notification::query()->count(),
            'Second --apply run must not create additional notifications.'
        );
    }

    public function test_cancelled_rows_are_never_touched_by_apply(): void
    {
        // Surgical fixture: one mission with a single cancelled candidature on a
        // pending payment with fedapay_transaction_id = null. Guards against any
        // future regression that widens the status filter to include 'cancelled'.
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 0,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'fedapay_transaction_id' => null,
            'status' => MissionPaymentStatus::Pending,
        ]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $cancelled = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Cancelled,
        ]);

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        $this->assertDatabaseHas('candidatures', [
            'id' => $cancelled->id,
            'status' => CandidatureStatus::Cancelled->value,
        ]);
    }

    public function test_command_exits_success_when_no_stale_selections_exist(): void
    {
        // No fixtures at all: nothing in scope.
        $this->artisan('candidature:reconcile-stale-selections', [
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('No stale selections to reconcile')
            ->assertSuccessful();
    }

    public function test_apply_exits_success_when_no_stale_selections_exist(): void
    {
        // Mirror of the dry-run empty-scope test on the apply path.
        // Runbook §6 tells operators that a re-run after a clean apply must exit
        // success with "No stale selections to reconcile"; lock the behaviour.
        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
        ])
            ->expectsOutputToContain('No stale selections to reconcile')
            ->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_apply_skips_mission_status_reset_when_mission_is_already_out_of_pending_payment(): void
    {
        // A mission already reverted to `published` (e.g. by a human between
        // audit and apply) must still have its candidatures and payment
        // reconciled, but the mission row itself must NOT be re-written.
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 0,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'fedapay_transaction_id' => null,
            'status' => MissionPaymentStatus::Pending,
        ]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $rejected = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Rejected,
        ]);

        $missionUpdatedAtBefore = $mission->fresh()->updated_at;

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])->assertSuccessful();

        $this->assertDatabaseHas('candidatures', [
            'id' => $rejected->id,
            'status' => CandidatureStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('mission_payments', [
            'id' => $payment->id,
            'status' => MissionPaymentStatus::Failed->value,
        ]);
        // Mission status stays `published` — the command logged
        // `mission_status_reset_skipped: already_in_expected_state` and did not
        // write the row, so `updated_at` is unchanged.
        $missionAfter = $mission->fresh();
        $this->assertSame(MissionStatus::Published, $missionAfter->status);
        $this->assertEquals($missionUpdatedAtBefore, $missionAfter->updated_at);
    }

    public function test_discovery_excludes_payments_with_non_null_fedapay_transaction_id(): void
    {
        // Defence-in-depth: the audit SQL narrows to fedapay_transaction_id IS NULL.
        // A payment with a non-null transaction id, even if its status is not `paid`,
        // must not be discovered — it represents a live FedaPay attempt that should
        // be resolved via the normal recovery runbook, not via this command.
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 0,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'fedapay_transaction_id' => '42',
            'status' => MissionPaymentStatus::Pending,
        ]);

        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $rejected = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Rejected,
        ]);

        $this->artisan('candidature:reconcile-stale-selections', [
            '--apply' => true,
            '--notifications-per-second' => 1000,
        ])
            ->expectsOutputToContain('No stale selections to reconcile')
            ->assertSuccessful();

        $this->assertDatabaseHas('candidatures', [
            'id' => $rejected->id,
            'status' => CandidatureStatus::Rejected->value,
        ]);
        $this->assertSame(0, Notification::query()->count());
    }
}
