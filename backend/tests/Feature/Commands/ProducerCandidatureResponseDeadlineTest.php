<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Mail\CandidatureResponseReminderMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

/**
 * Lot B6 (décision D3) — le Producer a 72 h pour répondre aux candidatures, comptées depuis la
 * clôture des candidatures (fin du jour date_limite_candidature, fuseau métier), ou jusqu'au
 * début du jour de tournage s'il arrive plus tôt. Relance à 48 h ; les candidatures restées en
 * attente passent ensuite `expired`, Face prévenue. Jamais une candidature d'une sélection
 * en cours de paiement.
 */
class ProducerCandidatureResponseDeadlineTest extends TestCase
{
    use BuildsPendingMissionSelection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-19 10:00:00', 'UTC'));
        $this->setUpSelectionProducer();
        $this->producerUser->update(['email' => 'producer@test.bj']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function mission(array $overrides = []): Mission
    {
        return Mission::factory()->published()->forAll()->create(array_merge([
            'producer_id' => $this->producer->id,
            'titre' => 'Spot TV jus de bissap',
            'date_limite_candidature' => '2026-10-20',
            'date_tournage' => '2026-11-15',
        ], $overrides));
    }

    private function candidature(Mission $mission, CandidatureStatus $status = CandidatureStatus::Pending): Candidature
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);
    }

    // --- Échéance ---

    public function test_deadline_is_72_hours_after_the_end_of_the_application_deadline_day(): void
    {
        // Fin du 20/10 à Porto-Novo = 20/10 23:00 UTC ; + 72 h.
        $this->assertTrue($this->mission()->producerResponseDeadline()->equalTo(Carbon::parse('2026-10-23 23:00:00', 'UTC')));
    }

    public function test_deadline_is_the_start_of_the_shooting_day_when_earlier(): void
    {
        $mission = $this->mission(['date_tournage' => '2026-10-22']);

        $this->assertTrue($mission->producerResponseDeadline()->equalTo(Carbon::parse('2026-10-21 23:00:00', 'UTC')));
    }

    // --- Expiration ---

    public function test_unanswered_candidatures_expire_at_the_deadline_and_the_face_is_told(): void
    {
        $mission = $this->mission();
        $pending = $this->candidature($mission);
        $accepted = $this->candidature($mission, CandidatureStatus::Accepted);
        $rejected = $this->candidature($mission, CandidatureStatus::Rejected);

        $this->travelTo(Carbon::parse('2026-10-23 22:59:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures')->assertExitCode(Command::SUCCESS);
        $this->assertSame(CandidatureStatus::Pending, $pending->fresh()->status);

        $this->travelTo(Carbon::parse('2026-10-23 23:00:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures')
            ->expectsOutputToContain('Candidatures expired: 1')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(CandidatureStatus::Expired, $pending->fresh()->status);
        $this->assertSame(CandidatureStatus::Accepted, $accepted->fresh()->status);
        $this->assertSame(CandidatureStatus::Rejected, $rejected->fresh()->status);

        $faceUserId = User::query()->where('userable_type', Face::class)->where('userable_id', $pending->face_id)->value('id');
        $notification = Notification::query()->where('user_id', $faceUserId)->where('type', 'candidature_expired_no_response')->sole();
        $this->assertStringContainsString("Le producteur n'a pas répondu à votre candidature pour la mission « Spot TV jus de bissap »", $notification->data['message']);
        $this->assertSame("/face/missions/{$mission->uuid}", $notification->data['url']);

        // Idempotent.
        $this->artisan('missions:expire-unanswered-candidatures')->expectsOutputToContain('Candidatures expired: 0');
    }

    public function test_candidatures_whose_deadline_passed_long_ago_expire_silently(): void
    {
        // Historique au déploiement : échéance dépassée depuis plus de 24 h → expirée, Face non notifiée.
        $old = $this->candidature($this->mission());
        // Échéance dépassée depuis moins de 24 h → expirée et notifiée.
        $recent = $this->candidature($this->mission(['date_limite_candidature' => '2026-10-22']));

        // Échéances : 23/10 23:00 UTC (ancienne) et 25/10 23:00 UTC (récente).
        $this->travelTo(Carbon::parse('2026-10-26 01:00:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures')
            ->expectsOutputToContain('Candidatures expired: 2')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame(CandidatureStatus::Expired, $old->fresh()->status);
        $this->assertSame(CandidatureStatus::Expired, $recent->fresh()->status);

        $userIdOf = fn (Candidature $c): int => (int) User::query()->where('userable_type', Face::class)->where('userable_id', $c->face_id)->value('id');
        $this->assertFalse(Notification::query()->where('user_id', $userIdOf($old))->where('type', 'candidature_expired_no_response')->exists());
        $this->assertTrue(Notification::query()->where('user_id', $userIdOf($recent))->where('type', 'candidature_expired_no_response')->exists());
    }

    public function test_an_expired_candidature_tells_the_face_the_producer_did_not_answer(): void
    {
        $mission = $this->mission();
        $pending = $this->candidature($mission);
        $this->travelTo(Carbon::parse('2026-10-24 00:00:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures');

        $pending->refresh();
        $this->assertSame('expired', $pending->displayStatus());
        $this->assertSame('Sans réponse du producteur', $pending->displayStatusLabel());

        // Une place expirée faute de confirmation de la Face (lot B3) garde son libellé.
        $selected = $this->candidature($mission, CandidatureStatus::Expired);
        $selected->update(['accepted_at' => now()->subDays(3)]);
        $this->assertSame('Expirée', $selected->fresh()->displayStatusLabel());
    }

    public function test_candidatures_of_a_selection_being_paid_never_expire(): void
    {
        [$mission, , $faces] = $this->pendingSelection(2);
        $mission->update(['date_limite_candidature' => '2026-10-10', 'date_tournage' => '2026-11-15']);
        $outsider = $this->candidature($mission);

        $this->travelTo(Carbon::parse('2026-10-25 00:00:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures')->assertExitCode(Command::SUCCESS);

        foreach ($faces as $face) {
            $this->assertSame(CandidatureStatus::Pending, $face['candidature']->fresh()->status);
        }
        // Mission en attente de paiement : rien n'expire, même hors sélection.
        $this->assertSame(CandidatureStatus::Pending, $outsider->fresh()->status);
    }

    public function test_a_candidature_with_a_pending_payment_entry_never_expires(): void
    {
        // Acceptation UGC hybride en cours de paiement : entry Pending, mission encore publiée.
        $mission = $this->mission(['type_mission' => 'ugc']);
        $candidature = $this->candidature($mission);
        MissionPaymentCandidature::create([
            'mission_payment_id' => null,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 14250,
            'escrow_status' => EscrowStatus::Pending,
        ]);

        $this->travelTo(Carbon::parse('2026-10-25 00:00:00', 'UTC'));
        $this->artisan('missions:expire-unanswered-candidatures')->assertExitCode(Command::SUCCESS);

        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
    }

    public function test_missions_whose_deadline_is_ahead_are_left_alone(): void
    {
        $mission = $this->mission(['date_limite_candidature' => '2026-10-30']);
        $pending = $this->candidature($mission);

        $this->artisan('missions:expire-unanswered-candidatures')->expectsOutputToContain('Candidatures expired: 0');

        $this->assertSame(CandidatureStatus::Pending, $pending->fresh()->status);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
    }

    // --- Relance à 48 h ---

    public function test_the_producer_is_reminded_once_when_24_hours_remain(): void
    {
        $mission = $this->mission();
        $this->candidature($mission);
        $this->candidature($mission);

        $this->travelTo(Carbon::parse('2026-10-22 22:59:00', 'UTC'));
        $this->artisan('missions:remind-candidature-response')->expectsOutputToContain('Reminders sent: 0');

        $this->travelTo(Carbon::parse('2026-10-22 23:00:00', 'UTC'));
        $this->artisan('missions:remind-candidature-response')
            ->expectsOutputToContain('Reminders sent: 1')
            ->assertExitCode(Command::SUCCESS);
        $this->artisan('missions:remind-candidature-response')->expectsOutputToContain('Reminders sent: 0');

        $notification = Notification::query()
            ->where('user_id', $this->producerUser->id)
            ->where('type', 'mission_candidatures_response_reminder')
            ->sole();
        // 23/10 23:00 UTC = 24/10 00:00 à Porto-Novo.
        $this->assertStringContainsString('2 candidature(s) attendent votre réponse', $notification->data['message']);
        $this->assertStringContainsString('Répondez avant le 24/10 à 00:00', $notification->data['message']);

        Mail::assertQueued(CandidatureResponseReminderMail::class, 1);
        Mail::assertQueued(CandidatureResponseReminderMail::class, fn (CandidatureResponseReminderMail $mail): bool => $mail->hasTo('producer@test.bj'));
        $this->assertNotNull($mission->fresh()->candidature_response_reminder_sent_at);
    }

    public function test_no_reminder_without_pending_candidatures(): void
    {
        $mission = $this->mission();
        $this->candidature($mission, CandidatureStatus::Accepted);

        $this->travelTo(Carbon::parse('2026-10-23 10:00:00', 'UTC'));
        $this->artisan('missions:remind-candidature-response')->expectsOutputToContain('Reminders sent: 0');

        Mail::assertNothingQueued();
    }
}
