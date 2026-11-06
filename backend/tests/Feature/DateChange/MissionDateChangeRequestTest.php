<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\CandidatureStatus;
use App\Enums\DateChangeResponseStatus;
use App\Enums\DateChangeStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Mail\DateChangeMail;
use App\Models\DateChangeRequest;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Producteur : demande de changement de date d'une mission payée (POST /api/v1/producer/missions/{mission}/date-change).
 */
class MissionDateChangeRequestTest extends DateChangeTestCase
{
    public function test_request_changes_the_date_creates_lines_for_confirmed_and_in_progress_only_and_notifies(): void
    {
        [$mission, , $faces] = $this->paidMission([
            CandidatureStatus::Confirmed,
            CandidatureStatus::InProgress,
            CandidatureStatus::Accepted,
        ]);

        $this->requestMissionChange($mission, '2026-11-25', 'meteo')
            ->assertOk()
            ->assertJsonPath('data.date_tournage', Carbon::parse('2026-11-25')->toIso8601String())
            ->assertJsonPath('data.date_change.used', 1)
            ->assertJsonPath('data.date_change.max', 2)
            ->assertJsonPath('data.date_change.pending', true)
            ->assertJsonPath('data.date_change.can_change', false)
            ->assertJsonPath('data.date_change.latest.status', 'pending')
            ->assertJsonPath('data.date_change.latest.reason_label', 'Météo')
            ->assertJsonPath('data.date_change.latest.old_date', '2026-11-20')
            ->assertJsonPath('data.date_change.latest.new_date', '2026-11-25')
            ->assertJsonCount(2, 'data.date_change.latest.responses');

        // Date changée et rappel du jour de tournage remis à zéro ; la clôture ne bouge pas.
        $mission->refresh();
        $this->assertSame('2026-11-25', $mission->date_tournage->toDateString());
        $this->assertSame('2026-11-05', $mission->date_limite_candidature->toDateString());
        $this->assertNull($mission->shooting_reminder_sent_at);

        // Échéance : min(maintenant + 48 h, début de la nouvelle date).
        $request = DateChangeRequest::query()->sole();
        $this->assertSame(DateChangeStatus::Pending, $request->status);
        $this->assertSame('producer', $request->requested_by_role);
        $this->assertSame($this->producerUser->id, $request->requested_by_user_id);
        $this->assertSame('2026-11-12 09:00:00', $request->respond_by->utc()->format('Y-m-d H:i:s'));
        $this->assertNotEmpty($request->uuid);

        // Lignes : Confirmed et InProgress seulement, pas Accepted.
        $this->assertDatabaseCount('date_change_responses', 2);
        foreach ([0, 1] as $i) {
            $this->assertDatabaseHas('date_change_responses', [
                'date_change_request_id' => $request->id,
                'candidature_id' => $faces[$i]['candidature']->id,
                'status' => DateChangeResponseStatus::Pending->value,
            ]);
        }
        $this->assertDatabaseMissing('date_change_responses', ['candidature_id' => $faces[2]['candidature']->id]);

        $expected = 'Le tournage de « Spot test » passe au mercredi 25 novembre. Confirmez avant le jeudi 12 novembre à 10:00.';
        foreach ([0, 1] as $i) {
            $notification = Notification::query()
                ->where('user_id', $faces[$i]['faceUser']->id)
                ->where('type', 'mission_date_change_requested')
                ->sole();
            $this->assertSame($expected, $notification->data['message']);
            $this->assertSame("/face/missions/{$mission->uuid}", $notification->data['url']);

            Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faces[$i]['faceUser']->email)
                && $mail->subjectText === 'Votre tournage passe au mercredi 25 novembre : confirmez avant le jeudi 12 novembre à 10:00');
        }

        // Face acceptée : informée seulement, sans ligne ni demande de réponse.
        $accepted = Notification::query()->where('user_id', $faces[2]['faceUser']->id)->sole();
        $this->assertSame('mission_date_changed', $accepted->type);
        $this->assertSame('Le tournage de « Spot test » passe au mercredi 25 novembre.', $accepted->data['message']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faces[2]['faceUser']->email)
            && $mail->subjectText === 'La date de tournage a changé : mercredi 25 novembre');

        $this->assertSame(0, Notification::query()->where('user_id', $this->producerUser->id)->count());
        Mail::assertQueuedCount(3);
    }

    public function test_respond_by_is_the_start_of_the_new_date_when_it_comes_before_48_hours(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed], '2026-11-20');
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'UTC'));

        // Nouvelle date au 12/11 : début de journée = 11/11 23:00 UTC. L'échéance est 1 h avant, soit la veille
        // à 23:00 heure du Bénin (22:00 UTC), pour que le cron passe avant le pointage de minuit.
        $this->requestMissionChange($mission, '2026-11-12')->assertOk();

        $respondBy = DateChangeRequest::query()->sole()->respond_by;
        $this->assertSame('2026-11-11 22:00:00', $respondBy->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-11 23:00:00', $respondBy->copy()->setTimezone('Africa/Porto-Novo')->format('Y-m-d H:i:s'));
    }

    public function test_request_without_any_face_to_confirm_is_resolved_immediately(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Accepted]);

        $this->requestMissionChange($mission)->assertOk()->assertJsonPath('data.date_change.pending', false);

        $request = DateChangeRequest::query()->sole();
        $this->assertSame(DateChangeStatus::Completed, $request->status);
        $this->assertNotNull($request->resolved_at);
        $this->assertDatabaseCount('date_change_responses', 0);
    }

    public function test_producer_detail_exposes_the_date_change_block(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed, CandidatureStatus::InProgress, CandidatureStatus::Accepted]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.can_change', true)
            ->assertJsonPath('data.date_change.used', 0)
            ->assertJsonPath('data.date_change.faces_to_confirm', 2)
            ->assertJsonPath('data.date_change.pending', false)
            ->assertJsonPath('data.date_change.latest', null);

        // Pas de requête de détail sur les listes : la clé est omise.
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions')
            ->assertOk()
            ->assertJsonMissingPath('data.0.date_change');
    }

    public function test_it_rejects_ugc_missions(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed], '2026-11-20', ['type_mission' => MissionType::Ugc]);

        $this->assertDateError($this->requestMissionChange($mission), 'UGC');
        $this->assertDatabaseCount('date_change_requests', 0);
    }

    public function test_it_rejects_an_unpaid_mission(): void
    {
        [$mission, $payment] = $this->paidMission([CandidatureStatus::Confirmed]);
        $payment->update(['status' => MissionPaymentStatus::Pending]);

        $this->assertDateError($this->requestMissionChange($mission), 'mission payée');
        $this->assertSame('2026-11-20', $mission->fresh()->date_tournage->toDateString());
    }

    public function test_it_rejects_a_mission_that_is_not_closed(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed], '2026-11-20', ['status' => 'published']);

        $this->assertDateError($this->requestMissionChange($mission), 'mission payée');
    }

    public function test_it_rejects_a_request_less_than_12_hours_before_the_current_date(): void
    {
        // Tournage le 10/11 : début de journée = 09/11 23:00 UTC, déjà dépassé.
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed], '2026-11-10');

        $this->assertDateError($this->requestMissionChange($mission, '2026-11-25'), 'trop tard');
    }

    public function test_it_rejects_the_same_date(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        $this->assertDateError($this->requestMissionChange($mission, '2026-11-20'), 'différente');
    }

    public function test_it_rejects_a_new_date_starting_in_less_than_12_hours(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);
        // 13:00 UTC : le 11/11 commence à 10/11 23:00 UTC, dans 10 h seulement.
        Carbon::setTestNow(Carbon::parse('2026-11-10 13:00:00', 'UTC'));

        $this->assertDateError($this->requestMissionChange($mission, '2026-11-11'), 'trop proche');
        $this->assertDateError($this->requestMissionChange($mission, '2026-11-10'), 'trop proche');
        // Le 12/11 commence dans 34 h : accepté.
        $this->requestMissionChange($mission, '2026-11-12')->assertOk();
    }

    public function test_it_rejects_a_third_change_even_when_the_previous_ones_are_resolved(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);
        $this->existingRequest($mission, DateChangeStatus::Completed);
        $this->existingRequest($mission, DateChangeStatus::Completed);

        $this->assertDateError($this->requestMissionChange($mission), 'maximal');
        $this->assertDatabaseCount('date_change_requests', 2);
    }

    public function test_it_rejects_a_second_request_while_one_is_pending(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
        $this->assertDateError($this->requestMissionChange($mission, '2026-11-27'), 'déjà en cours');

        $this->assertDatabaseCount('date_change_requests', 1);
        $this->assertSame('2026-11-25', $mission->fresh()->date_tournage->toDateString());
    }

    public function test_another_producer_gets_403(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);
        $other = \App\Models\Producer::factory()->create();
        $otherUser = \App\Models\User::factory()->create(['userable_type' => \App\Models\Producer::class, 'userable_id' => $other->id]);

        $this->requestMissionChange($mission, '2026-11-25', 'meteo', $otherUser)->assertForbidden();
        $this->assertDatabaseCount('date_change_requests', 0);
    }

    public function test_reason_is_required_and_must_be_in_the_list(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/date-change", ['new_date' => '2026-11-25'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->requestMissionChange($mission, '2026-11-25', 'texte libre')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_request_is_accepted_up_to_the_exact_boundary_the_day_before_at_noon_benin_time(): void
    {
        // Tournage le 20/11 : début de journée 19/11 23:00 UTC ; limite = veille à midi heure du Bénin = 19/11 11:00 UTC.
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:59:59', 'UTC'));
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
    }

    public function test_request_is_refused_from_the_exact_boundary_the_day_before_at_noon_benin_time(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        // 11:00:00 UTC pile = 12:00:00 au Bénin : refusé (strictement avant la limite seulement).
        Carbon::setTestNow(Carbon::parse('2026-11-19 11:00:00', 'UTC'));
        $this->assertDateError($this->requestMissionChange($mission, '2026-11-25'), 'trop tard');

        Carbon::setTestNow(Carbon::parse('2026-11-19 11:00:01', 'UTC'));
        $this->assertDateError($this->requestMissionChange($mission, '2026-11-25'), 'trop tard');
        $this->assertDatabaseCount('date_change_requests', 0);
    }

    public function test_the_new_date_boundary_is_exactly_12_hours_from_now(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        // Le 11/11 commence à 10/11 23:00 UTC (minuit au Bénin). À 11:00:01 UTC il reste moins de 12 h.
        Carbon::setTestNow(Carbon::parse('2026-11-10 11:00:01', 'UTC'));
        $this->assertDateError($this->requestMissionChange($mission, '2026-11-11'), 'trop proche');

        // À 11:00:00 UTC pile : exactement 12 h, accepté.
        Carbon::setTestNow(Carbon::parse('2026-11-10 11:00:00', 'UTC'));
        $this->requestMissionChange($mission, '2026-11-11')->assertOk();
    }

    public function test_the_postponement_is_capped_at_30_days_and_max_date_is_exposed(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertJsonPath('data.date_change.max_date', '2026-12-20');

        // 20/11 + 30 jours = 20/12 : accepté pile, refusé au jour suivant.
        $this->assertDateError($this->requestMissionChange($mission, '2026-12-21'), 'plus de 30 jours');
        $this->assertDatabaseCount('date_change_requests', 0);
        $this->requestMissionChange($mission, '2026-12-20')->assertOk();
    }
}
