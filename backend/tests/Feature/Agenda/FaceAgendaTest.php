<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\FaceUnavailableDay;
use App\Models\User;
use Tests\Feature\DateChange\DateChangeTestCase;

/**
 * Calendrier de la Face : GET /api/v1/face/agenda et PUT /api/v1/face/agenda/unavailable-days.
 * Horloge figée (DateChangeTestCase) : mardi 10/11/2026 09:00 UTC, soit le 10/11 en jour métier.
 */
class FaceAgendaTest extends DateChangeTestCase
{
    private function getAgenda(User $as, string $from = '2026-11-01', string $to = '2026-11-30'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as)->getJson("/api/v1/face/agenda?from={$from}&to={$to}");
    }

    /** @param  array<string, mixed>  $body */
    private function putDays(User $as, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as)->putJson('/api/v1/face/agenda/unavailable-days', $body);
    }

    private function block(User $faceUser, string ...$days): void
    {
        foreach ($days as $day) {
            FaceUnavailableDay::create(['face_id' => $faceUser->userable_id, 'day' => $day]);
        }
    }

    private function escrow(Booking $booking, EscrowStatus $status = EscrowStatus::Locked): EscrowTransaction
    {
        return EscrowTransaction::create([
            'booking_id' => $booking->id,
            'amount' => $booking->montant_face_recoit,
            'status' => $status,
            'locked_at' => now()->subDay(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET
    // -------------------------------------------------------------------------

    public function test_the_agenda_has_the_exact_shape_and_lists_engaged_events(): void
    {
        $faceUser = $this->faceUser();
        $this->block($faceUser, '2026-11-13', '2026-11-12');

        [$booking] = $this->cashBooking([
            'status' => BookingStatus::Paid,
            'type_contenu' => 'Clip vidéo',
            'date_debut' => '2026-11-18 08:00:00',
            'date_fin' => '2026-11-20 16:00:00',
        ], $faceUser);
        $this->escrow($booking);

        [$mission, $payment] = $this->paidMission([], '2026-11-24', ['titre' => 'Pub boisson']);
        $this->selectedFaceFor($faceUser, $mission, $payment, CandidatureStatus::Confirmed);

        $response = $this->getAgenda($faceUser)->assertOk();

        $response->assertJsonPath('data.from', '2026-11-01')
            ->assertJsonPath('data.to', '2026-11-30')
            ->assertJsonPath('data.today', '2026-11-10')
            ->assertJsonPath('data.unavailable_days', ['2026-11-12', '2026-11-13'])
            ->assertJsonCount(2, 'data.events')
            ->assertJsonStructure(['data' => ['from', 'to', 'today', 'unavailable_days', 'events' => [['kind', 'uuid', 'title', 'status', 'state', 'start', 'end', 'counterpart', 'amount_face', 'secured', 'start_time', 'end_time']], 'upcoming_earnings' => ['amount', 'count']]]);

        $producerName = $this->producer->display_name;

        $this->assertSame([
            'kind' => 'booking',
            'uuid' => $booking->uuid,
            'title' => 'Clip vidéo',
            'status' => 'paid',
            'state' => 'engaged',
            'start' => '2026-11-18',
            'end' => '2026-11-20',
            'counterpart' => $producerName,
            'amount_face' => 90000,
            'secured' => true,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ], $response->json('data.events.0'));

        $this->assertSame([
            'kind' => 'mission',
            'uuid' => $mission->uuid,
            'title' => 'Pub boisson',
            'status' => 'confirmed',
            'state' => 'engaged',
            'start' => '2026-11-24',
            'end' => '2026-11-24',
            'counterpart' => $producerName,
            'amount_face' => 90000,
            'secured' => true,
            'start_time' => null,
            'end_time' => null,
        ], $response->json('data.events.1'));
    }

    public function test_an_unpaid_accepted_booking_is_not_secured(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['status' => BookingStatus::Accepted, 'date_debut' => '2026-11-16 09:00:00', 'date_fin' => '2026-11-16 17:00:00'], $faceUser);

        $event = $this->getAgenda($faceUser)->assertOk()->json('data.events.0');

        $this->assertFalse($event['secured']);
        $this->assertSame(90000, $event['amount_face']);
    }

    public function test_the_hours_are_in_the_business_timezone(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['date_debut' => '2026-11-18 08:00:00', 'date_fin' => '2026-11-18 16:30:00'], $faceUser);

        $event = $this->getAgenda($faceUser)->assertOk()->json('data.events.0');

        $this->assertSame('09:00', $event['start_time']);
        $this->assertSame('17:30', $event['end_time']);
    }

    public function test_upcoming_earnings_sums_locked_escrows_regardless_of_the_range(): void
    {
        $faceUser = $this->faceUser();

        // Compté : booking à séquestre bloqué dans la plage (90 000).
        [$inRange] = $this->cashBooking(['status' => BookingStatus::Paid, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-18 17:00:00'], $faceUser);
        $this->escrow($inRange);

        // Compté : booking hors plage from/to mais encore bloqué (90 000).
        [$outOfRange] = $this->cashBooking(['status' => BookingStatus::Paid, 'date_debut' => '2026-12-20 09:00:00', 'date_fin' => '2026-12-20 17:00:00'], $faceUser);
        $this->escrow($outOfRange);

        // Compté : mission engagée (90 000).
        [$mission, $payment] = $this->paidMission([], '2026-11-24');
        $this->selectedFaceFor($faceUser, $mission, $payment, CandidatureStatus::Confirmed);

        // Ignoré : séquestre libéré.
        [$released] = $this->cashBooking(['status' => BookingStatus::Completed, 'date_debut' => '2026-11-03 09:00:00', 'date_fin' => '2026-11-03 17:00:00'], $faceUser);
        $this->escrow($released, EscrowStatus::Released);

        // Ignoré : entrée de mission libérée (freed_at).
        [$mission2, $payment2] = $this->paidMission([], '2026-11-26');
        ['entry' => $freed] = $this->selectedFaceFor($faceUser, $mission2, $payment2, CandidatureStatus::Confirmed);
        $freed->forceFill(['freed_at' => now()])->save();

        // Ignoré : séquestre d'une autre Face (booking et mission).
        $other = $this->faceUser();
        [$otherBooking] = $this->cashBooking(['status' => BookingStatus::Paid, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-18 17:00:00'], $other);
        $this->escrow($otherBooking);
        [$mission3, $payment3] = $this->paidMission([], '2026-11-27');
        $this->selectedFaceFor($other, $mission3, $payment3, CandidatureStatus::Confirmed);

        $this->getAgenda($faceUser)->assertOk()
            ->assertJsonPath('data.upcoming_earnings', ['amount' => 270000, 'count' => 3]);

        // Indépendant de la plage demandée.
        $this->getAgenda($faceUser, '2026-11-01', '2026-11-02')->assertOk()
            ->assertJsonPath('data.upcoming_earnings', ['amount' => 270000, 'count' => 3]);
    }

    public function test_upcoming_earnings_is_zero_without_locked_escrow(): void
    {
        $this->getAgenda($this->faceUser())->assertOk()
            ->assertJsonPath('data.upcoming_earnings', ['amount' => 0, 'count' => 0]);
    }

    public function test_states_pending_engaged_and_done_and_excluded_statuses(): void
    {
        $faceUser = $this->faceUser();

        $this->cashBooking(['status' => BookingStatus::Pending, 'date_debut' => '2026-11-14 09:00:00', 'date_fin' => '2026-11-14 17:00:00'], $faceUser);
        $this->cashBooking(['status' => BookingStatus::Accepted, 'date_debut' => '2026-11-16 09:00:00', 'date_fin' => '2026-11-16 17:00:00'], $faceUser);
        $this->cashBooking(['status' => BookingStatus::Completed, 'date_debut' => '2026-11-03 09:00:00', 'date_fin' => '2026-11-03 17:00:00'], $faceUser);
        $this->cashBooking(['status' => BookingStatus::Refused, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-18 17:00:00'], $faceUser);
        $this->cashBooking(['status' => BookingStatus::CancelledByFace, 'date_debut' => '2026-11-19 09:00:00', 'date_fin' => '2026-11-19 17:00:00'], $faceUser);
        // Booking UGC sans dates : jamais dans l'agenda.
        $this->cashBooking(['status' => BookingStatus::Accepted, 'type_contenu' => 'UGC', 'date_debut' => null, 'date_fin' => null], $faceUser);

        $events = $this->getAgenda($faceUser)->assertOk()->json('data.events');

        $this->assertSame(['done', 'pending', 'engaged'], array_column($events, 'state'));
        $this->assertSame(['2026-11-03', '2026-11-14', '2026-11-16'], array_column($events, 'start'));
        $this->assertSame(['completed', 'pending', 'accepted'], array_column($events, 'status'));
    }

    public function test_an_event_overlapping_the_window_is_included_and_one_outside_is_not(): void
    {
        $faceUser = $this->faceUser();

        $this->cashBooking(['date_debut' => '2026-10-29 09:00:00', 'date_fin' => '2026-11-02 17:00:00'], $faceUser);
        $this->cashBooking(['date_debut' => '2026-12-05 09:00:00', 'date_fin' => '2026-12-06 17:00:00'], $faceUser);

        $response = $this->getAgenda($faceUser)->assertOk()->assertJsonCount(1, 'data.events');

        $this->assertSame('2026-10-29', $response->json('data.events.0.start'));
        $this->assertSame('2026-11-02', $response->json('data.events.0.end'));
    }

    public function test_a_booking_stored_at_2330_utc_the_day_before_lands_on_the_business_day(): void
    {
        $faceUser = $this->faceUser();

        // 19/11 23:30 UTC = 20/11 00:30 à Porto-Novo ; fin 20/11 22:30 UTC = 20/11 23:30 locale.
        $this->cashBooking(['date_debut' => '2026-11-19 23:30:00', 'date_fin' => '2026-11-20 22:30:00'], $faceUser);

        $event = $this->getAgenda($faceUser, '2026-11-20', '2026-11-20')->assertOk()->json('data.events.0');

        $this->assertSame('2026-11-20', $event['start']);
        $this->assertSame('2026-11-20', $event['end']);
        // La veille locale ne contient rien.
        $this->getAgenda($faceUser, '2026-11-19', '2026-11-19')->assertOk()->assertJsonCount(0, 'data.events');
    }

    public function test_the_agenda_is_scoped_to_the_face(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking([], $this->faceUser());
        $this->block($this->faceUser(), '2026-11-12');

        $this->getAgenda($faceUser)->assertOk()
            ->assertJsonCount(0, 'data.events')
            ->assertJsonPath('data.unavailable_days', []);
    }

    public function test_the_range_is_validated(): void
    {
        $faceUser = $this->faceUser();

        $this->getAgenda($faceUser, '2026-11-01', '2026-11-30')->assertOk();
        // 92 jours d'écart : accepté ; 93 : refusé.
        $this->getAgenda($faceUser, '2026-01-01', '2026-04-03')->assertOk();
        $this->getAgenda($faceUser, '2026-01-01', '2026-04-04')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getAgenda($faceUser, '2026-11-10', '2026-11-09')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->actingAs($faceUser)->getJson('/api/v1/face/agenda')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->actingAs($faceUser)->getJson('/api/v1/face/agenda?from=10/11/2026&to=2026-11-30')->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_a_producer_cannot_read_the_face_agenda(): void
    {
        $this->getAgenda($this->producerUser)->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // PUT
    // -------------------------------------------------------------------------

    public function test_adding_and_removing_days_returns_all_blocked_days_of_the_horizon(): void
    {
        $faceUser = $this->faceUser();
        $this->block($faceUser, '2026-11-12', '2026-11-15');

        $this->putDays($faceUser, ['add' => ['2026-11-28'], 'remove' => ['2026-11-12']])
            ->assertOk()
            ->assertJsonPath('message', 'Agenda mis à jour')
            ->assertJsonPath('data.unavailable_days', ['2026-11-15', '2026-11-28']);

        $this->assertSame(
            ['2026-11-15', '2026-11-28'],
            FaceUnavailableDay::query()->where('face_id', $faceUser->userable_id)->orderBy('day')->get()->map(fn ($d) => $d->day->toDateString())->all(),
        );
    }

    public function test_the_response_only_lists_days_between_today_and_one_year(): void
    {
        $faceUser = $this->faceUser();
        // Jour passé (hérité) et jour au-delà de l'horizon : hors de la réponse.
        $this->block($faceUser, '2026-11-05', '2027-12-01');

        $this->putDays($faceUser, ['add' => ['2026-11-12']])
            ->assertOk()
            ->assertJsonPath('data.unavailable_days', ['2026-11-12']);
    }

    public function test_adding_a_blocked_day_and_removing_a_free_day_are_noops(): void
    {
        $faceUser = $this->faceUser();
        $this->block($faceUser, '2026-11-12');

        $this->putDays($faceUser, ['add' => ['2026-11-12'], 'remove' => ['2026-11-20']])
            ->assertOk()
            ->assertJsonPath('data.unavailable_days', ['2026-11-12']);

        $this->assertSame(1, FaceUnavailableDay::query()->count());
    }

    public function test_the_same_request_twice_gives_the_same_result(): void
    {
        $faceUser = $this->faceUser();
        $body = ['add' => ['2026-11-12', '2026-11-13'], 'remove' => ['2026-11-14']];

        $first = $this->putDays($faceUser, $body)->assertOk()->json('data.unavailable_days');
        $second = $this->putDays($faceUser, $body)->assertOk()->json('data.unavailable_days');

        $this->assertSame($first, $second);
        $this->assertSame(2, FaceUnavailableDay::query()->count());
    }

    public function test_today_is_modifiable_and_a_past_day_is_refused(): void
    {
        $faceUser = $this->faceUser();

        $this->putDays($faceUser, ['add' => ['2026-11-10']])->assertOk();

        $response = $this->putDays($faceUser, ['add' => ['2026-11-09']])->assertUnprocessable();
        $this->assertStringContainsString('Ce jour est passé ou trop lointain.', json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));

        $this->block($faceUser, '2026-11-08');
        $this->putDays($faceUser, ['remove' => ['2026-11-08']])->assertUnprocessable();
        $this->assertDatabaseHas('face_unavailable_days', ['day' => '2026-11-08']);
    }

    public function test_the_horizon_is_365_days(): void
    {
        $faceUser = $this->faceUser();

        // Aujourd'hui (10/11/2026) + 365 jours = 10/11/2027.
        $this->putDays($faceUser, ['add' => ['2027-11-10']])->assertOk();

        $response = $this->putDays($faceUser, ['add' => ['2027-11-11']])->assertUnprocessable();
        $this->assertStringContainsString('Ce jour est passé ou trop lointain.', json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
    }

    public function test_a_day_engaged_by_an_active_booking_cannot_be_blocked(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-20 17:00:00'], $faceUser);

        $response = $this->putDays($faceUser, ['add' => ['2026-11-25', '2026-11-19']])->assertUnprocessable();

        $this->assertStringContainsString('Le 19 novembre est déjà engagé (booking ou mission).', json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, FaceUnavailableDay::query()->count());
    }

    public function test_a_pending_booking_does_not_prevent_blocking(): void
    {
        $faceUser = $this->faceUser();
        $this->cashBooking(['status' => BookingStatus::Pending, 'date_debut' => '2026-11-18 09:00:00', 'date_fin' => '2026-11-18 17:00:00'], $faceUser);

        $this->putDays($faceUser, ['add' => ['2026-11-18']])->assertOk();
    }

    public function test_a_day_engaged_by_a_mission_cannot_be_blocked(): void
    {
        $faceUser = $this->faceUser();
        [$mission, $payment] = $this->paidMission([], '2026-11-24');
        $this->selectedFaceFor($faceUser, $mission, $payment, CandidatureStatus::InProgress);

        $response = $this->putDays($faceUser, ['add' => ['2026-11-24']])->assertUnprocessable();

        $this->assertStringContainsString('Le 24 novembre est déjà engagé (booking ou mission).', json_encode($response->json('errors'), JSON_UNESCAPED_UNICODE));
    }

    public function test_the_same_date_in_add_and_remove_is_refused(): void
    {
        $faceUser = $this->faceUser();

        $this->putDays($faceUser, ['add' => ['2026-11-12'], 'remove' => ['2026-11-12']])->assertUnprocessable();

        $this->assertSame(0, FaceUnavailableDay::query()->count());
    }

    public function test_one_invalid_date_writes_nothing(): void
    {
        $faceUser = $this->faceUser();
        $this->block($faceUser, '2026-11-12');

        $this->putDays($faceUser, ['add' => ['2026-11-20', '2026-11-01'], 'remove' => ['2026-11-12']])->assertUnprocessable();

        $this->assertSame(['2026-11-12'], FaceUnavailableDay::query()->get()->map(fn ($d) => $d->day->toDateString())->all());
    }

    public function test_the_payload_is_validated(): void
    {
        $faceUser = $this->faceUser();

        $this->putDays($faceUser, [])->assertUnprocessable();
        $this->putDays($faceUser, ['add' => [], 'remove' => []])->assertUnprocessable();
        $this->putDays($faceUser, ['add' => ['12/11/2026']])->assertUnprocessable()->assertJsonValidationErrors('add.0');
        $this->putDays($faceUser, ['add' => ['2026-11-12', '2026-11-12']])->assertUnprocessable();
        $this->putDays($faceUser, ['add' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('add');

        $tooMany = array_map(fn (int $i): string => \Illuminate\Support\Carbon::parse('2026-11-11')->addDays($i)->toDateString(), range(0, 62));
        $this->putDays($faceUser, ['add' => $tooMany])->assertUnprocessable()->assertJsonValidationErrors('add');

        // Un seul des deux tableaux suffit.
        $this->putDays($faceUser, ['remove' => ['2026-11-20']])->assertOk();
    }

    public function test_a_producer_cannot_update_unavailable_days(): void
    {
        $this->putDays($this->producerUser, ['add' => ['2026-11-12']])->assertForbidden();

        $this->assertSame(0, FaceUnavailableDay::query()->count());
    }
}
