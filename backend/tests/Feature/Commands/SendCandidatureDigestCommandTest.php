<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Mail\NewCandidaturesDigestMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

/**
 * Lot D5 — e-mail groupé « nouvelles candidatures » : au plus un par mission et par heure,
 * résumé des candidatures en attente créées depuis le dernier envoi.
 */
class SendCandidatureDigestCommandTest extends TestCase
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

    private function mission(): Mission
    {
        return Mission::factory()->published()->forAll()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Spot TV jus de bissap',
            'date_limite_candidature' => '2026-10-25',
            'date_tournage' => '2026-11-15',
            'candidature_digest_sent_at' => null,
        ]);
    }

    private function candidature(Mission $mission, CandidatureStatus $status = CandidatureStatus::Pending, string $prenom = 'Awa', string $ville = 'Cotonou'): Candidature
    {
        $face = Face::factory()->create(['prenom' => $prenom, 'ville' => $ville]);

        return Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $status,
        ]);
    }

    private function runDigest(): void
    {
        $this->artisan('missions:send-candidature-digest')->assertExitCode(Command::SUCCESS);
    }

    public function test_digest_summarises_new_pending_candidatures_for_the_producer(): void
    {
        $mission = $this->mission();
        $this->candidature($mission, prenom: 'Awa', ville: 'Cotonou');
        $this->candidature($mission, prenom: 'Koffi', ville: 'Porto-Novo');
        // Retirée et traitée : ne comptent pas.
        $this->candidature($mission, CandidatureStatus::Cancelled);
        $this->candidature($mission, CandidatureStatus::Rejected);

        $this->runDigest();

        Mail::assertQueued(NewCandidaturesDigestMail::class, function (NewCandidaturesDigestMail $mail): bool {
            $this->assertSame(2, $mail->newCount);
            $this->assertSame(2, $mail->totalPending);
            $this->assertSame([['name' => 'Awa', 'city' => 'Cotonou'], ['name' => 'Koffi', 'city' => 'Porto-Novo']], $mail->faces);
            $this->assertSame('Nouvelles candidatures pour « Spot TV jus de bissap »', $mail->envelope()->subject);
            $html = $mail->render();
            $this->assertStringContainsString('Voir les candidatures', $html);
            $this->assertStringContainsString('?tab=candidatures', $html);

            return $mail->hasTo('producer@test.bj');
        });
        $this->assertNotNull($mission->fresh()->candidature_digest_sent_at);
    }

    public function test_digest_lists_at_most_five_faces(): void
    {
        $mission = $this->mission();
        foreach (range(1, 7) as $i) {
            $this->candidature($mission, prenom: "Face{$i}");
        }

        $this->runDigest();

        Mail::assertQueued(NewCandidaturesDigestMail::class, function (NewCandidaturesDigestMail $mail): bool {
            return $mail->newCount === 7 && count($mail->faces) === 5;
        });
    }

    public function test_nothing_is_sent_without_new_pending_candidature(): void
    {
        $mission = $this->mission();
        $this->candidature($mission, CandidatureStatus::Cancelled);
        $this->candidature($mission, CandidatureStatus::Accepted);

        $this->runDigest();

        Mail::assertNothingQueued();
        $this->assertNull($mission->fresh()->candidature_digest_sent_at);
    }

    public function test_command_is_idempotent_and_sends_at_most_one_digest_per_hour(): void
    {
        $mission = $this->mission();
        $this->candidature($mission);

        $this->runDigest();
        $this->runDigest();
        Mail::assertQueuedCount(1);

        // Nouvelle candidature moins d'une heure après : rien tant que l'heure n'est pas écoulée.
        $this->travelTo(now()->addMinutes(30));
        $this->candidature($mission, prenom: 'Koffi');
        $this->runDigest();
        Mail::assertQueuedCount(1);

        // Passé l'heure : un seul nouvel e-mail, qui ne résume que la candidature arrivée depuis.
        $this->travelTo(now()->addMinutes(31));
        $this->runDigest();
        $this->runDigest();
        Mail::assertQueuedCount(2);
        Mail::assertQueued(NewCandidaturesDigestMail::class, function (NewCandidaturesDigestMail $mail): bool {
            return $mail->newCount === 1 && $mail->totalPending === 2 && $mail->faces[0]['name'] === 'Koffi';
        });
    }

    public function test_hourly_run_started_slightly_before_the_previous_send_time_still_sends(): void
    {
        $mission = $this->mission();
        $this->candidature($mission);

        // Premier passage traité quelques secondes après le début de l'heure.
        $this->travelTo(now()->addSeconds(40));
        $this->runDigest();
        Mail::assertQueuedCount(1);

        // Passage horaire suivant, démarré à l'heure pile : moins de 60 min après l'envoi précédent.
        $this->travelTo(Carbon::parse('2026-10-19 10:30:00', 'UTC'));
        $this->candidature($mission, prenom: 'Koffi');
        $this->travelTo(Carbon::parse('2026-10-19 11:00:00', 'UTC'));
        $this->runDigest();

        Mail::assertQueuedCount(2);
    }

    public function test_missions_that_are_no_longer_recruiting_are_skipped(): void
    {
        $mission = $this->mission();
        $mission->update(['status' => 'closed']);
        $this->candidature($mission);

        $this->runDigest();

        Mail::assertNothingQueued();
    }

    public function test_mail_failure_is_logged_and_retried_on_the_next_run(): void
    {
        $mission = $this->mission();
        $this->candidature($mission);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('smtp down'));

        $this->runDigest();

        $this->assertNull($mission->fresh()->candidature_digest_sent_at);
    }
}
