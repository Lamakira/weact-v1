<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\CandidatureStatus;
use App\Enums\DateChangeResponseStatus;
use App\Enums\DateChangeStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionType;
use App\Mail\DateChangeMail;
use App\Mail\FaceConfirmationExpiredMail;
use App\Models\Candidature;
use App\Models\DateChangeRequest;
use App\Models\ImageContract;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Face : confirme ou se retire après un changement de date
 * (POST /api/v1/face/candidatures/{candidature}/date-change/respond).
 */
class MissionDateChangeRespondTest extends DateChangeTestCase
{
    /**
     * Mission payée, deux Faces confirmées, changement de date demandé par le Producteur (20/11 vers 25/11).
     *
     * @return array{0: Mission, 1: MissionPayment, 2: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: \App\Models\User, face: \App\Models\Face}>, 3: DateChangeRequest}
     */
    private function missionWithPendingChange(int $faceCount = 2): array
    {
        [$mission, $payment, $faces] = $this->paidMission(array_fill(0, $faceCount, CandidatureStatus::Confirmed));

        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
        Mail::fake();

        return [$mission, $payment, $faces, DateChangeRequest::query()->sole()];
    }

    /** Autre mission payée où la même Face est engagée à la date donnée. */
    private function engageElsewhere(array $face, string $date, CandidatureStatus $status = CandidatureStatus::Confirmed): Mission
    {
        [$other, $otherPayment] = $this->paidMission([], $date);
        $candidature = Candidature::factory()->create([
            'mission_id' => $other->id,
            'face_id' => $face['face']->id,
            'status' => $status,
            'accepted_at' => now()->subDay(),
        ]);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $otherPayment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face['face']->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now()->subDays(3),
        ]);

        return $other;
    }

    public function test_confirmations_complete_the_request_and_send_the_summary_to_the_producer(): void
    {
        [$mission, , $faces, $request] = $this->missionWithPendingChange();

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.can_respond', false)
            ->assertJsonPath('data.new_date', '2026-11-25');

        // Une réponse manque encore : ni résumé ni clôture.
        $this->assertSame(DateChangeStatus::Pending, $request->fresh()->status);
        $this->assertSame(0, Notification::where('type', 'mission_date_change_summary')->count());
        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);

        $this->respondAsFace($faces[1]['faceUser'], $faces[1]['candidature'], 'confirm')->assertOk();

        $request->refresh();
        $this->assertSame(DateChangeStatus::Completed, $request->status);
        $this->assertNotNull($request->resolved_at);

        $summary = Notification::where('type', 'mission_date_change_summary')->sole();
        $this->assertSame($this->producerUser->id, $summary->user_id);
        $this->assertSame('Réponses à la nouvelle date : 2 confirmées, 0 place libérée', $summary->data['message']);
        $this->assertSame("/producer/missions/{$mission->uuid}", $summary->data['url']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($this->producerUser->email)
            && $mail->subjectText === 'Réponses à la nouvelle date : 2 confirmées, 0 place libérée');
    }

    public function test_the_face_mission_page_exposes_the_date_change_and_the_producer_sees_responses(): void
    {
        [$mission, , $faces] = $this->missionWithPendingChange();

        $this->actingAs($faces[0]['faceUser'])
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.old_date', '2026-11-20')
            ->assertJsonPath('data.date_change.new_date', '2026-11-25')
            ->assertJsonPath('data.date_change.reason_label', 'Météo')
            ->assertJsonPath('data.date_change.status', 'pending')
            ->assertJsonPath('data.date_change.conflict', false)
            ->assertJsonPath('data.date_change.can_respond', true);

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertOk();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.pending', true)
            ->assertJsonPath('data.date_change.latest.responses.0.status', 'confirmed')
            ->assertJsonPath('data.date_change.latest.responses.0.candidature_uuid', $faces[0]['candidature']->uuid)
            ->assertJsonPath('data.date_change.latest.responses.1.status', 'pending');
    }

    public function test_a_face_without_a_line_sees_no_date_change(): void
    {
        [$mission, $payment] = $this->missionWithPendingChange(1);
        $late = $this->selectedFace($mission, $payment, CandidatureStatus::Accepted);

        $this->actingAs($late['faceUser'])
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change', null);

        $this->respondAsFace($late['faceUser'], $late['candidature'], 'confirm')->assertUnprocessable();
    }

    public function test_confirm_is_refused_when_the_face_has_an_active_booking_on_the_new_date(): void
    {
        [$mission, , $faces, $request] = $this->missionWithPendingChange();
        $this->cashBooking([
            'date_debut' => '2026-11-24 20:00:00',
            'date_fin' => '2026-11-25 10:00:00',
        ], $faces[0]['faceUser']);

        $this->actingAs($faces[0]['faceUser'])
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertJsonPath('data.date_change.conflict', true);

        $this->assertDateError($this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm'), 'engagement');

        $this->assertSame(DateChangeResponseStatus::Pending, $request->responses()->where('candidature_id', $faces[0]['candidature']->id)->sole()->status);

        // Se retirer reste possible malgré le conflit.
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertOk();
    }

    public function test_confirm_is_refused_when_the_face_is_committed_to_another_mission_that_day(): void
    {
        [, , $faces] = $this->missionWithPendingChange();
        $this->engageElsewhere($faces[0], '2026-11-25');

        $this->assertDateError($this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm'), 'engagement');
        $this->assertDatabaseHas('date_change_responses', [
            'candidature_id' => $faces[0]['candidature']->id,
            'status' => 'pending',
        ]);

        // L'autre Face, libre, confirme sans problème.
        $this->respondAsFace($faces[1]['faceUser'], $faces[1]['candidature'], 'confirm')->assertOk();
    }

    public function test_a_mission_on_another_day_or_a_freed_place_is_not_a_conflict(): void
    {
        [, , $faces] = $this->missionWithPendingChange();
        $this->engageElsewhere($faces[0], '2026-11-26');

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertOk();
    }

    public function test_withdraw_frees_the_place_and_keeps_the_money_in_escrow_when_a_replacement_is_possible(): void
    {
        [$mission, , $faces, $request] = $this->missionWithPendingChange();
        $this->replacementCandidate($mission);
        $contract = ImageContract::create([
            'candidature_id' => $faces[0]['candidature']->id,
            'template_version' => 'v1',
            'finalite' => 'Campagne',
            'supports' => ['web'],
            'territoire' => 'benin',
            'duree_annees' => 1,
            'snapshot' => [],
            'producer_signed_name' => 'Producteur Test',
            'producer_signed_at' => now()->subDay(),
            'producer_signed_email_masked' => 'p***@example.com',
            'face_signed_name' => 'Face Test',
            'face_signed_at' => now()->subHour(),
            'face_signed_email_masked' => 'f***@example.com',
        ]);

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn')
            ->assertJsonPath('data.can_respond', false);

        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertNotNull($entry->freed_at);
        $this->assertNull($entry->refunded_at);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertSame(0, WalletTransaction::where('user_id', $this->producerUser->id)->count());

        $this->assertNotNull($contract->fresh()->voided_at);

        $response = $request->responses()->where('candidature_id', $faces[0]['candidature']->id)->sole();
        $this->assertSame(DateChangeResponseStatus::Withdrawn, $response->status);
        $this->assertNotNull($response->responded_at);
        $this->assertSame(DateChangeStatus::Pending, $request->fresh()->status);

        $notification = Notification::where('type', 'mission_date_change_withdrawn')->sole();
        $this->assertSame($this->producerUser->id, $notification->user_id);
        $this->assertStringContainsString('a quitté la mission « Spot test » après le changement de date : sa place est libérée.', $notification->data['message']);
        $this->assertStringContainsString('avant le 25/11 à 00:00', $notification->data['message']);
        $this->assertStringContainsString(Money::format(100000), $notification->data['message']);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $notification->data['url']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($this->producerUser->email)
            && str_ends_with($mail->subjectText, 'se retire : remplacez cette Face ou récupérez le budget de la place'));

        // Pas de mail à la Face qui se retire elle-même, ni de réutilisation du mail d'expiration.
        Mail::assertNotQueued(FaceConfirmationExpiredMail::class);
        Mail::assertNotQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faces[0]['faceUser']->email));
        // Seule la notification de la demande initiale existe : rien de plus pour la Face qui se retire.
        $this->assertSame(['mission_date_change_requested'], Notification::where('user_id', $faces[0]['faceUser']->id)->pluck('type')->all());

        // La deuxième Face confirme : résumé avec la place libérée.
        $this->respondAsFace($faces[1]['faceUser'], $faces[1]['candidature'], 'confirm')->assertOk();
        $this->assertSame(DateChangeStatus::Completed, $request->fresh()->status);
        $this->assertSame(
            'Réponses à la nouvelle date : 1 confirmée, 1 place libérée',
            Notification::where('type', 'mission_date_change_summary')->sole()->data['message'],
        );
    }

    public function test_withdraw_refunds_the_producer_immediately_the_exact_place_budget_once(): void
    {
        [, , $faces] = $this->missionWithPendingChange();

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertOk();

        $entry = $faces[0]['entry']->fresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertNotNull($entry->refunded_at);
        $this->assertNull($entry->freed_at);
        $this->assertSame(CandidatureStatus::Expired, $faces[0]['candidature']->fresh()->status);

        // budget_par_face (100 000), pas le net Face (90 000).
        $this->assertSame(100000, $this->producerUser->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->producerUser->id,
            'type' => 'credit',
            'amount' => 100000,
            'description' => 'Mission : Face retirée après changement de date, Spot test',
        ]);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)->count());

        $notification = Notification::where('type', 'mission_date_change_withdrawn')->sole();
        $this->assertStringContainsString('sa place est libérée et '.Money::format(100000).' ont été crédités sur votre wallet.', $notification->data['message']);

        // Double réponse : refusée, aucun second crédit ni seconde notification.
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertUnprocessable();
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertUnprocessable();

        $this->assertSame(100000, $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)->count());
        $this->assertSame(1, Notification::where('type', 'mission_date_change_withdrawn')->count());
    }

    public function test_a_second_confirmation_is_refused_without_side_effect(): void
    {
        [, , $faces] = $this->missionWithPendingChange();

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertOk();
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertUnprocessable();
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertUnprocessable();

        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
    }

    public function test_responding_after_the_deadline_is_refused(): void
    {
        [, , $faces, $request] = $this->missionWithPendingChange();

        // respond_by = 12/11 09:00 UTC ; à cet instant précis la réponse est déjà refusée.
        Carbon::setTestNow($request->respond_by->copy());

        $this->assertDateError($this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm'), 'dépassé');
        $this->assertDateError($this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw'), 'dépassé');

        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(EscrowStatus::Locked, $faces[0]['entry']->fresh()->escrow_status);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
    }

    public function test_another_face_cannot_answer_for_the_candidature(): void
    {
        [, , $faces] = $this->missionWithPendingChange();

        $this->respondAsFace($faces[1]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertForbidden();
        $this->respondAsFace($this->producerUser, $faces[0]['candidature'], 'withdraw')->assertForbidden();
        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);
    }

    public function test_decision_is_validated(): void
    {
        [, , $faces] = $this->missionWithPendingChange();

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'peut-etre')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
    }

    public function test_a_withdrawn_face_no_longer_appears_in_the_attendance_form(): void
    {
        [$mission, , $faces] = $this->missionWithPendingChange();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertOk()
            ->assertJsonCount(2, 'data.entries');

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertOk();

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/attendance-form")
            ->assertOk()
            ->assertJsonCount(1, 'data.entries');

        $this->assertSame($faces[1]['entry']->id, $response->json('data.entries.0.entry_id') ?? $response->json('data.entries.0.id'));
    }

    public function test_the_withdrawal_is_refused_for_an_ugc_or_unpaid_mission_state(): void
    {
        [$mission, $payment, $faces] = $this->missionWithPendingChange();
        $payment->update(['status' => MissionPaymentStatus::Refunded]);

        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertUnprocessable();
        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);

        $payment->update(['status' => MissionPaymentStatus::Paid]);
        $mission->update(['type_mission' => MissionType::Ugc]);
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'withdraw')->assertUnprocessable();
        $this->assertSame(0, $this->producerUser->fresh()->balance);
    }
}
