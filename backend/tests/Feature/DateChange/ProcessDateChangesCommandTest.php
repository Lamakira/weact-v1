<?php

declare(strict_types=1);

namespace Tests\Feature\DateChange;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DateChangeResponseStatus;
use App\Enums\DateChangeStatus;
use App\Enums\EscrowStatus;
use App\Mail\DateChangeMail;
use App\Models\DateChangeRequest;
use App\Models\Notification;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Cron `date-changes:process` : relance unique à mi-parcours, puis expiration à l'échéance.
 * Demande créée le 10/11 à 09:00 UTC, échéance le 12/11 à 09:00 UTC, mi-parcours le 11/11 à 09:00 UTC.
 */
class ProcessDateChangesCommandTest extends DateChangeTestCase
{
    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    }

    public function test_a_single_reminder_is_sent_to_pending_faces_only_at_mid_course(): void
    {
        [$mission, , $faces] = $this->paidMission([CandidatureStatus::Confirmed, CandidatureStatus::Confirmed]);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertOk();
        Mail::fake();

        // Avant la mi-parcours : rien.
        $this->at('2026-11-11 08:59:59');
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->assertSame(0, Notification::where('type', 'date_change_reminder')->count());

        $this->at('2026-11-11 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $reminder = Notification::where('type', 'date_change_reminder')->sole();
        $this->assertSame($faces[1]['faceUser']->id, $reminder->user_id);
        $this->assertSame('Plus que 24 h pour répondre à la nouvelle date de « Spot test ».', $reminder->data['message']);
        $this->assertSame("/face/missions/{$mission->uuid}", $reminder->data['url']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faces[1]['faceUser']->email)
            && $mail->subjectText === 'Plus que 24 h pour répondre à la nouvelle date');
        Mail::assertQueuedCount(1);
        $this->assertNotNull(DateChangeRequest::query()->sole()->reminder_sent_at);

        // Exécutions répétées, plus tard : toujours une seule relance.
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->at('2026-11-11 20:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->assertSame(1, Notification::where('type', 'date_change_reminder')->count());
        Mail::assertQueuedCount(1);
    }

    public function test_the_reminder_hours_are_rounded_up(): void
    {
        [$mission] = $this->paidMission([CandidatureStatus::Confirmed]);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();

        // Reste 23 h 30 avant l'échéance (12/11 09:00 UTC) : arrondi à 24 h.
        $this->at('2026-11-11 09:30:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $this->assertStringContainsString('Plus que 24 h', Notification::where('type', 'date_change_reminder')->sole()->data['message']);
    }

    public function test_an_expired_mission_request_withdraws_silent_faces_and_frees_their_place(): void
    {
        [$mission, , $faces] = $this->paidMission([CandidatureStatus::Confirmed, CandidatureStatus::InProgress]);
        $this->replacementCandidate($mission);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
        $this->respondAsFace($faces[0]['faceUser'], $faces[0]['candidature'], 'confirm')->assertOk();
        Mail::fake();

        $this->at('2026-11-12 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $request = DateChangeRequest::query()->sole();
        $this->assertSame(DateChangeStatus::Completed, $request->status);
        $this->assertSame(DateChangeResponseStatus::Expired, $request->responses()->where('candidature_id', $faces[1]['candidature']->id)->sole()->status);
        $this->assertSame(DateChangeResponseStatus::Confirmed, $request->responses()->where('candidature_id', $faces[0]['candidature']->id)->sole()->status);

        // Même mécanisme que l'expiration B3 : plus aucune Face à confirmer, la confirmée passe en cours.
        $this->assertSame(CandidatureStatus::InProgress, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(CandidatureStatus::Expired, $faces[1]['candidature']->fresh()->status);

        // Place libérée : l'argent reste en séquestre.
        $entry = $faces[1]['entry']->fresh();
        $this->assertSame(EscrowStatus::Locked, $entry->escrow_status);
        $this->assertNotNull($entry->freed_at);
        $this->assertSame(0, $this->producerUser->fresh()->balance);

        $expired = Notification::where('type', 'mission_date_change_expired')->sole();
        $this->assertSame($faces[1]['faceUser']->id, $expired->user_id);
        $this->assertSame("Sans réponse de votre part, vous avez quitté la mission « Spot test ». Aucune pénalité n'a été appliquée.", $expired->data['message']);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->hasTo($faces[1]['faceUser']->email));

        $this->assertSame(1, Notification::where('type', 'mission_date_change_withdrawn')->count());
        $this->assertSame(
            'Réponses à la nouvelle date : 1 confirmée, 1 place libérée',
            Notification::where('type', 'mission_date_change_summary')->sole()->data['message'],
        );

        // Idempotence : une exécution de plus ne change rien.
        $notifications = Notification::count();
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->assertSame($notifications, Notification::count());
    }

    public function test_an_expired_mission_request_refunds_the_place_once_when_nobody_can_replace(): void
    {
        [$mission, , $faces] = $this->paidMission([CandidatureStatus::Confirmed, CandidatureStatus::Confirmed]);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();

        $this->at('2026-11-12 09:00:01');
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->artisan('date-changes:process')->assertSuccessful();

        foreach ($faces as $face) {
            $this->assertSame(CandidatureStatus::Expired, $face['candidature']->fresh()->status);
            $this->assertSame(EscrowStatus::Refunded, $face['entry']->fresh()->escrow_status);
        }

        $this->assertSame(200000, $this->producerUser->fresh()->balance);
        $this->assertSame(2, WalletTransaction::where('user_id', $this->producerUser->id)->count());
        $this->assertSame(2, Notification::where('type', 'mission_date_change_expired')->count());
        $this->assertSame(
            'Réponses à la nouvelle date : 0 confirmée, 2 places libérées',
            Notification::where('type', 'mission_date_change_summary')->sole()->data['message'],
        );
    }

    public function test_a_request_is_untouched_before_its_deadline(): void
    {
        [$mission, , $faces] = $this->paidMission([CandidatureStatus::Confirmed]);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();

        $this->at('2026-11-12 08:59:59');
        $this->artisan('date-changes:process')->assertSuccessful();

        $this->assertSame(DateChangeStatus::Pending, DateChangeRequest::query()->sole()->status);
        $this->assertSame(CandidatureStatus::Confirmed, $faces[0]['candidature']->fresh()->status);
    }

    public function test_a_candidature_no_longer_engaged_is_closed_without_withdrawal(): void
    {
        [$mission, , $faces] = $this->paidMission([CandidatureStatus::Confirmed]);
        $this->requestMissionChange($mission, '2026-11-25')->assertOk();
        // Annulée entre-temps par un autre chemin : rien à rembourser ici.
        $faces[0]['candidature']->update(['status' => CandidatureStatus::Cancelled]);

        $this->at('2026-11-12 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $this->assertSame(DateChangeStatus::Completed, DateChangeRequest::query()->sole()->status);
        $this->assertSame(0, $this->producerUser->fresh()->balance);
        $this->assertSame(0, Notification::where('type', 'mission_date_change_expired')->count());
    }

    public function test_booking_proposal_reminder_goes_to_the_other_party_once(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->actingAs($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/date-change", [
            'new_date' => '2026-11-25',
            'reason' => 'meteo',
        ])->assertOk();
        Mail::fake();

        $this->at('2026-11-11 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->artisan('date-changes:process')->assertSuccessful();

        $reminder = Notification::where('type', 'date_change_reminder')->sole();
        $this->assertSame($faceUser->id, $reminder->user_id);
        $this->assertSame('Plus que 24 h pour répondre à la nouvelle date de votre tournage du vendredi 20 novembre.', $reminder->data['message']);
        $this->assertSame("/face/bookings/{$booking->uuid}", $reminder->data['url']);
        Mail::assertQueuedCount(1);
    }

    public function test_an_expired_booking_proposal_keeps_the_date_and_notifies_both_parties(): void
    {
        [$booking, $faceUser] = $this->cashBooking();
        $this->actingAs($faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/date-change", [
            'new_date' => '2026-11-25',
            'reason' => 'imprevu_personnel',
        ])->assertOk();
        Mail::fake();

        $this->at('2026-11-12 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $this->assertSame(DateChangeStatus::Expired, DateChangeRequest::query()->sole()->status);
        $this->assertSame('2026-11-20', $booking->fresh()->date_debut->toDateString());

        $kept = Notification::where('type', 'booking_date_change_kept')->get();
        $this->assertCount(2, $kept);
        $this->assertEqualsCanonicalizing([$faceUser->id, $this->producerUser->id], $kept->pluck('user_id')->all());
        Mail::assertQueuedCount(2);
        Mail::assertQueued(DateChangeMail::class, fn (DateChangeMail $mail): bool => $mail->subjectText === 'Le tournage reste le vendredi 20 novembre');

        // Idempotence.
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->assertSame(2, Notification::where('type', 'booking_date_change_kept')->count());
        Mail::assertQueuedCount(2);
    }

    public function test_an_expired_booking_proposal_on_a_cancelled_booking_expires_silently(): void
    {
        [$booking] = $this->cashBooking();
        $this->actingAs($this->producerUser)->postJson("/api/v1/bookings/{$booking->uuid}/date-change", [
            'new_date' => '2026-11-25',
            'reason' => 'meteo',
        ])->assertOk();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);
        Mail::fake();
        $before = Notification::count();

        // Le rappel à mi-parcours ne part pas non plus pour un booking annulé.
        $this->at('2026-11-11 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();
        $this->at('2026-11-12 09:00:00');
        $this->artisan('date-changes:process')->assertSuccessful();

        $this->assertSame(DateChangeStatus::Expired, DateChangeRequest::query()->sole()->status);
        $this->assertSame($before, Notification::count());
        Mail::assertNothingQueued();
    }
}
