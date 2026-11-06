<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DateChangeStatus;
use App\Mail\DateChangeMail;
use App\Models\Booking;
use App\Models\DateChangeRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/**
 * Booking : proposition de nouvelle date par une partie, réponse de l'autre
 * (POST/DELETE /api/v1/bookings/{booking}/date-change, POST .../date-change/respond).
 */
class BookingDateChangeTest extends DateChangeTestCase
{
    private function propose(Booking $booking, User $as, string $newDate = '2026-11-25', string $reason = 'meteo'): TestResponse
    {
        return $this->actingAs($as)->postJson("/api/v1/bookings/{$booking->uuid}/date-change", [
            'new_date' => $newDate,
            'reason' => $reason,
        ]);
    }

    private function answer(Booking $booking, User $as, string $decision): TestResponse
    {
        return $this->actingAs($as)->postJson("/api/v1/bookings/{$booking->uuid}/date-change/respond", ['decision' => $decision]);
    }

    private function withdrawProposal(Booking $booking, User $as): TestResponse
    {
        return $this->actingAs($as)->deleteJson("/api/v1/bookings/{$booking->uuid}/date-change");
    }

    public function test_the_producer_proposes_and_the_face_is_notified(): void
    {
        [$booking, $faceUser] = $this->cashBooking();

        $this->propose($booking, $this->producerUser)
            ->assertOk()
            ->assertJsonPath('data.date_debut', $booking->date_debut->toIso8601String())
            ->assertJsonPath('data.date_change.can_propose', false)
            ->assertJsonPath('data.date_change.used', 1)
            ->assertJsonPath('data.date_change.latest.status', 'pending')
            ->assertJsonPath('data.date_change.latest.proposed_by_me', true)
            ->assertJsonPath('data.date_change.latest.can_withdraw', true)
            ->assertJsonPath('data.date_change.latest.can_respond', false)
            ->assertJsonPath('data.date_change.latest.reason_label', 'Météo');

        $request = DateChangeRequest::query()->sole();
        $this->assertSame($booking->id, $request->booking_id);
        $this->assertNull($request->mission_id);
        $this->assertSame('producer', $request->requested_by_role);
        $this->assertSame('2026-11-20', $request->old_date->toDateString());
        $this->assertSame('2026-11-21', $request->old_end_date->toDateString());
        $this->assertSame('2026-11-25', $request->new_date->toDateString());
        $this->assertSame('2026-11-26', $request->new_end_date->toDateString());
        // min(maintenant + 48 h, début ancienne date, début nouvelle date)
        $this->assertSame('2026-11-12 09:00:00', $request->respond_by->utc()->format('Y-m-d H:i:s'));

        $notification = Notification::where('user_id', $faceUser->id)->sole();
        $this->assertSame('booking_date_change_proposed', $notification->type);
        $this->assertStringEndsWith('propose de déplacer votre tournage au mercredi 25 novembre. Répondez avant le jeudi 12 novembre à 10:00.', $notification->data['message']);
        $this->assertSame("/face/bookings/{$booking->uuid}", $notification->data['url']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faceUser->email)
            && $mail->subjectText === 'Proposition de nouvelle date pour votre tournage : mercredi 25 novembre');
        $this->assertSame(0, Notification::where('user_id', $this->producerUser->id)->count());
    }

    public function test_the_face_proposes_on_a_paid_booking_and_the_producer_is_notified(): void
    {
        [$booking, $faceUser] = $this->cashBooking(['status' => BookingStatus::Paid]);

        $this->propose($booking, $faceUser, '2026-11-18', 'imprevu_personnel')->assertOk();

        $this->assertSame('face', DateChangeRequest::query()->sole()->requested_by_role);
        $notification = Notification::where('user_id', $this->producerUser->id)->sole();
        $this->assertSame('booking_date_change_proposed', $notification->type);
        $this->assertSame("/producer/bookings/{$booking->uuid}", $notification->data['url']);
        // Début de l'ancienne date (19/11 23:00 UTC) plus tôt que +48 h ? Non : +48 h = 12/11 09:00 UTC reste le minimum.
        $this->assertSame('2026-11-12 09:00:00', DateChangeRequest::query()->sole()->respond_by->utc()->format('Y-m-d H:i:s'));
    }

    public function test_respond_by_is_capped_by_the_start_of_the_new_date(): void
    {
        [$booking] = $this->cashBooking();

        // Nouvelle date au 12/11 : début de journée = 11/11 23:00 UTC, avant maintenant + 48 h.
        $this->propose($booking, $this->producerUser, '2026-11-12')->assertOk();

        $this->assertSame('2026-11-11 23:00:00', DateChangeRequest::query()->sole()->respond_by->utc()->format('Y-m-d H:i:s'));
    }

    public function test_accepting_shifts_both_dates_keeps_the_duration_resets_reminders_and_leaves_amounts_untouched(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();
        Mail::fake();

        $this->answer($booking, $faceUser, 'accept')
            ->assertOk()
            ->assertJsonPath('data.date_change.latest.status', 'accepted')
            ->assertJsonPath('data.date_change.latest.can_respond', false)
            ->assertJsonPath('data.date_change.latest.can_withdraw', false)
            ->assertJsonPath('data.date_change.used', 1);

        $booking->refresh();
        $this->assertSame('2026-11-25 09:00:00', $booking->date_debut->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-26 17:00:00', $booking->date_fin->format('Y-m-d H:i:s'));
        $this->assertSame(10, $booking->duree_heures);
        $this->assertNull($booking->shooting_reminder_sent_at);
        $this->assertNull($booking->completion_reminder_sent_at);
        $this->assertSame(100000, $booking->tarif_base);
        $this->assertSame(110000, $booking->montant_total_producteur);
        $this->assertSame(90000, $booking->montant_face_recoit);
        $this->assertSame(BookingStatus::Accepted, $booking->status);

        $request = DateChangeRequest::query()->sole();
        $this->assertSame(DateChangeStatus::Accepted, $request->status);
        $this->assertNotNull($request->resolved_at);

        $notification = Notification::where('type', 'booking_date_change_accepted')->sole();
        $this->assertSame($this->producerUser->id, $notification->user_id);
        $this->assertStringEndsWith('a accepté la nouvelle date : le tournage a lieu le mercredi 25 novembre.', $notification->data['message']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($this->producerUser->email)
            && $mail->subjectText === 'Nouvelle date acceptée : mercredi 25 novembre');
    }

    public function test_the_producer_can_accept_a_proposal_from_the_face_and_a_date_can_move_backwards(): void
    {
        [$booking, $faceUser] = $this->cashBooking(['status' => BookingStatus::Paid]);
        $this->propose($booking, $faceUser, '2026-11-18')->assertOk();

        $this->answer($booking, $this->producerUser, 'accept')->assertOk();

        $booking->refresh();
        $this->assertSame('2026-11-18 09:00:00', $booking->date_debut->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 17:00:00', $booking->date_fin->format('Y-m-d H:i:s'));
        $this->assertSame(1, Notification::where('user_id', $faceUser->id)->where('type', 'booking_date_change_accepted')->count());
    }

    public function test_refusing_keeps_the_date_and_notifies_the_author(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();
        Mail::fake();

        $this->answer($booking, $faceUser, 'refuse')
            ->assertOk()
            ->assertJsonPath('data.date_change.latest.status', 'refused');

        $booking->refresh();
        $this->assertSame('2026-11-20 09:00:00', $booking->date_debut->format('Y-m-d H:i:s'));
        $this->assertNotNull($booking->shooting_reminder_sent_at);
        $this->assertSame(DateChangeStatus::Refused, DateChangeRequest::query()->sole()->status);

        $notification = Notification::where('type', 'booking_date_change_kept')->sole();
        $this->assertSame($this->producerUser->id, $notification->user_id);
        $this->assertStringEndsWith('a refusé la nouvelle date : le tournage reste le vendredi 20 novembre.', $notification->data['message']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($this->producerUser->email)
            && $mail->subjectText === 'Le tournage reste le vendredi 20 novembre');
        Mail::assertQueuedCount(1);
    }

    public function test_the_author_can_withdraw_a_pending_proposal_and_it_still_counts_in_the_quota(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();

        // Seul l'auteur retire.
        $this->withdrawProposal($booking, $faceUser)->assertForbidden();

        $this->withdrawProposal($booking, $this->producerUser)
            ->assertOk()
            ->assertJsonPath('data.date_change.latest.status', 'withdrawn')
            ->assertJsonPath('data.date_change.used', 1)
            ->assertJsonPath('data.date_change.can_propose', true);

        $this->assertSame(DateChangeStatus::Withdrawn, DateChangeRequest::query()->sole()->status);

        // Plus rien à retirer ni à accepter.
        $this->withdrawProposal($booking, $this->producerUser)->assertForbidden();
        $this->answer($booking, $faceUser, 'accept')->assertForbidden();
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
    }

    public function test_the_quota_counts_withdrawn_proposals(): void
    {
        [$booking] = $this->cashBooking();
        $this->existingRequest($booking, DateChangeStatus::Withdrawn);
        $this->existingRequest($booking, DateChangeStatus::Withdrawn);

        $this->assertDateError($this->propose($booking, $this->producerUser), 'maximal');
        $this->assertDatabaseCount('date_change_requests', 2);
    }

    public function test_the_author_cannot_answer_their_own_proposal(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();

        $this->answer($booking, $this->producerUser, 'accept')->assertForbidden();
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
        $this->assertSame(DateChangeStatus::Pending, DateChangeRequest::query()->sole()->status);

        // Un tiers n'a aucun accès.
        $this->answer($booking, $this->faceUser(), 'accept')->assertForbidden();
        $this->propose($booking, $this->faceUser())->assertForbidden();
    }

    public function test_pending_and_ugc_bookings_are_out_of_scope(): void
    {
        [$pending] = $this->cashBooking(['status' => BookingStatus::Pending]);
        [$ugc] = $this->cashBooking(['type_contenu' => 'UGC']);
        [$cancelled] = $this->cashBooking(['status' => BookingStatus::CancelledByProducer]);

        foreach ([$pending, $ugc, $cancelled] as $booking) {
            $this->propose($booking, $this->producerUser)->assertForbidden();
        }

        $this->assertDatabaseCount('date_change_requests', 0);
    }

    public function test_it_rejects_a_late_a_same_day_or_a_too_close_proposal(): void
    {
        [$late] = $this->cashBooking(['date_debut' => '2026-11-10 09:00:00', 'date_fin' => '2026-11-10 17:00:00']);
        $this->assertDateError($this->propose($late, $this->producerUser, '2026-11-25'), 'trop tard');

        [$booking] = $this->cashBooking();
        $this->assertDateError($this->propose($booking, $this->producerUser, '2026-11-20'), 'différente');

        Carbon::setTestNow(Carbon::parse('2026-11-10 13:00:00', 'UTC'));
        $this->assertDateError($this->propose($booking, $this->producerUser, '2026-11-11'), 'trop proche');
        $this->assertDatabaseCount('date_change_requests', 0);
    }

    public function test_a_second_proposal_is_refused_while_one_is_pending(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();

        $this->assertDateError($this->propose($booking, $faceUser, '2026-11-27'), 'déjà en cours');
    }

    public function test_the_proposal_boundary_is_the_day_before_at_noon_benin_time(): void
    {
        [$booking] = $this->cashBooking();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:59:59', 'UTC'));
        $this->propose($booking, $this->producerUser)->assertOk();
        $this->withdrawProposal($booking, $this->producerUser)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-19 11:00:00', 'UTC'));
        $this->assertDateError($this->propose($booking, $this->producerUser, '2026-11-26'), 'trop tard');
    }

    public function test_accepting_is_refused_when_the_face_has_an_active_booking_on_the_new_days(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->cashBooking(['date_debut' => '2026-11-26 08:00:00', 'date_fin' => '2026-11-26 18:00:00'], $faceUser);
        $this->propose($booking, $this->producerUser)->assertOk();

        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.latest.conflict', true);

        $this->assertDateError($this->answer($booking, $faceUser, 'accept'), 'engagement');
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
        $this->assertSame(DateChangeStatus::Pending, DateChangeRequest::query()->sole()->status);

        // Refuser reste possible.
        $this->answer($booking, $faceUser, 'refuse')->assertOk();
    }

    public function test_the_producer_accepting_a_face_proposal_is_blocked_by_the_face_agenda(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->cashBooking(['date_debut' => '2026-11-25 08:00:00', 'date_fin' => '2026-11-25 18:00:00'], $faceUser);
        $this->propose($booking, $faceUser)->assertOk();

        $this->assertDateError($this->answer($booking, $this->producerUser, 'accept'), 'agenda de la Face');
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
    }

    public function test_accepting_is_refused_when_the_face_is_committed_to_a_mission_on_the_new_days(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        [$mission, $payment] = $this->paidMission([], '2026-11-26');
        $mission2 = $this->selectedFaceFor($faceUser, $mission, $payment, CandidatureStatus::Confirmed);
        $this->propose($booking, $this->producerUser)->assertOk();

        $this->assertDateError($this->answer($booking, $faceUser, 'accept'), 'engagement');

        // La même mission, place libérée : plus un engagement.
        $mission2['entry']->update(['freed_at' => now()]);
        $this->answer($booking, $faceUser, 'accept')->assertOk();
    }

    public function test_a_booking_cancelled_during_the_wait_expires_the_request_without_notification(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);
        Mail::fake();
        $before = Notification::count();

        $this->assertStringContainsString('plus modifiable', (string) $this->answer($booking, $faceUser, 'accept')->assertUnprocessable()->json('errors.date.0'));

        $this->assertSame(DateChangeStatus::Expired, DateChangeRequest::query()->sole()->status);
        $this->assertSame($before, Notification::count());
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
        Mail::assertNothingQueued();
    }

    public function test_responding_after_the_deadline_is_refused(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();

        Carbon::setTestNow(DateChangeRequest::query()->sole()->respond_by->copy());

        $this->assertDateError($this->answer($booking, $faceUser, 'accept'), 'dépassé');
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());
    }

    public function test_the_detail_exposes_can_propose_and_hides_the_latest_once_the_date_has_passed(): void
    {
        [$booking, $faceUser] = $this->cashBooking();

        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.can_propose', true)
            ->assertJsonPath('data.date_change.used', 0)
            ->assertJsonPath('data.date_change.max', 2)
            ->assertJsonPath('data.date_change.latest', null);

        $this->propose($booking, $this->producerUser)->assertOk();

        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertJsonPath('data.date_change.latest.can_respond', true)
            ->assertJsonPath('data.date_change.latest.proposed_by_me', false)
            ->assertJsonPath('data.date_change.can_propose', false);

        // La liste ne porte pas le bloc (pas de requête par ligne).
        $this->actingAs($faceUser)->getJson('/api/v1/bookings')->assertOk()->assertJsonMissingPath('data.0.date_change');

        Carbon::setTestNow(Carbon::parse('2026-11-22 00:00:00', 'UTC'));
        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertJsonPath('data.date_change.latest', null);
    }

    public function test_new_date_and_reason_are_validated(): void
    {
        [$booking] = $this->cashBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/date-change", ['new_date' => 'demain', 'reason' => 'texte libre'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_date', 'reason']);
    }

    public function test_the_postponement_is_capped_at_30_days_and_max_date_is_exposed(): void
    {
        [$booking, $faceUser] = $this->cashBooking();

        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertJsonPath('data.date_change.max_date', '2026-12-20');

        $this->assertDateError($this->propose($booking, $this->producerUser, '2026-12-21'), 'plus de 30 jours');
        $this->assertDatabaseCount('date_change_requests', 0);
        $this->propose($booking, $this->producerUser, '2026-12-20')->assertOk();
    }

    public function test_latest_is_hidden_once_the_booking_is_cancelled(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);

        $this->actingAs($faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.date_change.latest', null)
            ->assertJsonPath('data.date_change.can_propose', false);
    }

    public function test_withdrawing_notifies_the_other_party(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->propose($booking, $this->producerUser)->assertOk();
        Mail::fake();

        $this->withdrawProposal($booking, $this->producerUser)->assertOk();

        $notification = Notification::where('type', 'booking_date_change_withdrawn')->sole();
        $this->assertSame($faceUser->id, $notification->user_id);
        $this->assertStringEndsWith('a retiré sa proposition : le tournage reste le vendredi 20 novembre.', $notification->data['message']);
        $this->assertSame("/face/bookings/{$booking->uuid}", $notification->data['url']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faceUser->email)
            && $mail->subjectText === 'Proposition de nouvelle date retirée : le tournage reste le vendredi 20 novembre');
        Mail::assertQueuedCount(1);
        $this->assertSame(0, Notification::where('user_id', $this->producerUser->id)->count());
    }

    /** Engage une Face existante sur une mission (candidature + séquestre). */
    private function selectedFaceFor(User $faceUser, \App\Models\Mission $mission, \App\Models\MissionPayment $payment, CandidatureStatus $status): array
    {
        $candidature = \App\Models\Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $faceUser->userable_id,
            'status' => $status,
            'accepted_at' => now()->subDay(),
        ]);
        $entry = \App\Models\MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $faceUser->userable_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => \App\Enums\EscrowStatus::Locked,
            'locked_at' => now()->subDays(3),
        ]);

        return ['candidature' => $candidature, 'entry' => $entry];
    }
}
