<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot D7 : filtre « À traiter » (scope Booking::toProcess) et tri par défaut « à traiter d'abord »
 * de GET /api/v1/bookings. Instant figé : mardi 20/10/2026 10:00 UTC (11:00 heure métier, UTC+1).
 */
class BookingToProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private User $otherFaceUser;

    private User $otherProducerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00:00');

        $this->faceUser = $this->makeUser(Face::class, Face::factory()->create()->id);
        $this->producerUser = $this->makeUser(Producer::class, Producer::factory()->create()->id);
        $this->otherFaceUser = $this->makeUser(Face::class, Face::factory()->create()->id);
        $this->otherProducerUser = $this->makeUser(Producer::class, Producer::factory()->create()->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $type, int $id): User
    {
        return User::factory()->create(['userable_type' => $type, 'userable_id' => $id]);
    }

    /**
     * @param  array<string, mixed>  $attrs  `created_at` accepté (posé en base, hors fillable)
     */
    private function booking(array $attrs = []): Booking
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $booking = Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'date_debut' => now()->addDays(10)->startOfDay(),
            'date_fin' => now()->addDays(10)->startOfDay(),
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('bookings')->where('id', $booking->id)->update(['created_at' => $createdAt]);
        }

        return $booking->fresh();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ugcBooking(BookingStatus $status, array $attrs = []): Booking
    {
        return $this->booking(array_merge([
            'status' => $status,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'nom_produit' => 'Sérum',
        ], $attrs));
    }

    private function withShipment(Booking $booking, UgcTunnelStatus $tunnel): Booking
    {
        $booking->shipment()->create([
            'transporteur' => 'DHL',
            'numero_suivi' => 'TRK123',
            'tunnel_status' => $tunnel,
            'shipped_at' => now()->subDay(),
            'destinataire_nom' => 'Face Test',
        ]);

        return $booking->fresh();
    }

    private function lockEscrow(Booking $booking): void
    {
        EscrowTransaction::factory()->create(['booking_id' => $booking->id, 'status' => 'locked']);
    }

    private function inProcess(Booking $booking, User $viewer): bool
    {
        return Booking::query()->toProcess($viewer)->whereKey($booking->id)->exists();
    }

    // ------------------------------------------------------------------
    // Face
    // ------------------------------------------------------------------

    public function test_face_cash_request_to_answer_while_window_is_open(): void
    {
        $fresh = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(1)]);
        $almostElapsed = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(71)]);
        $elapsed = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(73)]);
        // Tournage demain : 08 h heure métier demain (07:00 UTC) > maintenant -> encore ouvert.
        $shootTomorrow = $this->booking([
            'status' => BookingStatus::Pending,
            'created_at' => now()->subHours(1),
            'date_debut' => Carbon::parse('2026-10-21'),
        ]);
        // Tournage aujourd'hui : 08 h heure métier (07:00 UTC) déjà passé à 10:00 UTC -> fermé.
        $shootToday = $this->booking([
            'status' => BookingStatus::Pending,
            'created_at' => now()->subHours(1),
            'date_debut' => Carbon::parse('2026-10-20'),
        ]);

        $this->assertTrue($this->inProcess($fresh, $this->faceUser));
        $this->assertTrue($this->inProcess($almostElapsed, $this->faceUser));
        $this->assertFalse($this->inProcess($elapsed, $this->faceUser));
        $this->assertTrue($this->inProcess($shootTomorrow, $this->faceUser));
        $this->assertFalse($this->inProcess($shootToday, $this->faceUser));
    }

    public function test_face_ugc_request_to_answer_after_commission_within_acceptance_window(): void
    {
        $open = $this->ugcBooking(BookingStatus::CommissionPaid, ['commission_paid_at' => now()->subDays(2)]);
        $elapsed = $this->ugcBooking(BookingStatus::CommissionPaid, ['commission_paid_at' => now()->subDays(8)]);
        $refunded = $this->ugcBooking(BookingStatus::CommissionPaid, [
            'commission_paid_at' => now()->subDays(2),
            'commission_refunded_at' => now()->subDay(),
        ]);
        $commissionPending = $this->ugcBooking(BookingStatus::Pending, ['created_at' => now()->subHours(1)]);

        $this->assertTrue($this->inProcess($open, $this->faceUser));
        $this->assertFalse($this->inProcess($elapsed, $this->faceUser));
        $this->assertFalse($this->inProcess($refunded, $this->faceUser));
        // La commission à payer est l'affaire du Producteur, pas de la Face.
        $this->assertFalse($this->inProcess($commissionPending, $this->faceUser));
    }

    public function test_face_service_end_to_confirm(): void
    {
        $paidShootStarted = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->subDay()->startOfDay()]);
        $paidShootToday = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->startOfDay()]);
        $paidShootFuture = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->addDays(3)->startOfDay()]);
        $confirmedByProducer = $this->booking(['status' => BookingStatus::ConfirmedByProducer]);
        $confirmedByFace = $this->booking(['status' => BookingStatus::ConfirmedByFace, 'date_debut' => now()->subDay()->startOfDay()]);
        $completed = $this->booking(['status' => BookingStatus::Completed, 'date_debut' => now()->subDay()->startOfDay()]);

        $this->assertTrue($this->inProcess($paidShootStarted, $this->faceUser));
        $this->assertTrue($this->inProcess($paidShootToday, $this->faceUser));
        $this->assertFalse($this->inProcess($paidShootFuture, $this->faceUser));
        $this->assertTrue($this->inProcess($confirmedByProducer, $this->faceUser));
        // Déjà confirmé par la Face : l'action revient au Producteur.
        $this->assertFalse($this->inProcess($confirmedByFace, $this->faceUser));
        $this->assertFalse($this->inProcess($completed, $this->faceUser));
    }

    public function test_face_ugc_tunnel_reception_to_confirm_and_video_to_send(): void
    {
        $shipped = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Shipped);
        $received = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Received);
        $avisPending = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::AvisPending);
        $unboxingInReview = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::UnboxingInReview);
        $avisInReview = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::AvisInReview);
        $suspended = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Suspended);
        $noShipment = $this->ugcBooking(BookingStatus::Accepted);

        $this->assertTrue($this->inProcess($shipped, $this->faceUser));
        $this->assertTrue($this->inProcess($received, $this->faceUser));
        $this->assertTrue($this->inProcess($avisPending, $this->faceUser));
        // En revue chez le Producteur, suspendu, ou colis pas encore déclaré : rien à faire pour la Face.
        $this->assertFalse($this->inProcess($unboxingInReview, $this->faceUser));
        $this->assertFalse($this->inProcess($avisInReview, $this->faceUser));
        $this->assertFalse($this->inProcess($suspended, $this->faceUser));
        $this->assertFalse($this->inProcess($noShipment, $this->faceUser));
    }

    public function test_face_can_contest_no_show_and_late_cancellation_within_deadline(): void
    {
        $noShow = $this->booking(['status' => BookingStatus::NoShow, 'settlement_due_at' => now()->addHours(30)]);
        $lateCancel = $this->booking(['status' => BookingStatus::CancelledByProducer, 'settlement_due_at' => now()->addHours(30)]);
        $deadlinePassed = $this->booking(['status' => BookingStatus::NoShow, 'settlement_due_at' => now()->subMinute()]);
        $alreadyContested = $this->booking([
            'status' => BookingStatus::NoShow,
            'settlement_due_at' => now()->addHours(30),
            'disputed_at' => now()->subHour(),
        ]);
        $resolved = $this->booking([
            'status' => BookingStatus::NoShow,
            'settlement_due_at' => now()->addHours(30),
            'dispute_resolved_at' => now()->subHour(),
        ]);
        $noEscrow = $this->booking(['status' => BookingStatus::NoShow, 'settlement_due_at' => now()->addHours(30)]);
        $plainCancel = $this->booking(['status' => BookingStatus::CancelledByProducer, 'settlement_due_at' => null]);

        foreach ([$noShow, $lateCancel, $deadlinePassed, $alreadyContested, $resolved, $plainCancel] as $booking) {
            $this->lockEscrow($booking);
        }

        $this->assertTrue($this->inProcess($noShow, $this->faceUser));
        $this->assertTrue($this->inProcess($lateCancel, $this->faceUser));
        $this->assertFalse($this->inProcess($deadlinePassed, $this->faceUser));
        $this->assertFalse($this->inProcess($alreadyContested, $this->faceUser));
        $this->assertFalse($this->inProcess($resolved, $this->faceUser));
        // Sans séquestre bloqué la contestation est refusée par le service : rien à traiter.
        $this->assertFalse($this->inProcess($noEscrow, $this->faceUser));
        $this->assertFalse($this->inProcess($plainCancel, $this->faceUser));
    }

    public function test_face_has_nothing_to_process_on_producer_only_cases(): void
    {
        $acceptedToPay = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHour()]);
        $confirmedByFace = $this->booking(['status' => BookingStatus::ConfirmedByFace]);
        $ugcNoShipment = $this->ugcBooking(BookingStatus::Accepted);
        $ugcInReview = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::UnboxingInReview);

        foreach ([$acceptedToPay, $confirmedByFace, $ugcNoShipment, $ugcInReview] as $booking) {
            $this->assertFalse($this->inProcess($booking, $this->faceUser));
        }
    }

    // ------------------------------------------------------------------
    // Producer
    // ------------------------------------------------------------------

    public function test_producer_accepted_cash_booking_to_pay_while_window_is_open(): void
    {
        $open = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHours(2)]);
        $elapsed = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHours(25)]);
        $pending = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);

        $this->assertTrue($this->inProcess($open, $this->producerUser));
        $this->assertFalse($this->inProcess($elapsed, $this->producerUser));
        // Une demande cash encore en attente attend la Face, pas le Producteur.
        $this->assertFalse($this->inProcess($pending, $this->producerUser));
    }

    public function test_producer_ugc_commission_to_pay_while_window_is_open(): void
    {
        $open = $this->ugcBooking(BookingStatus::Pending, ['created_at' => now()->subHours(10)]);
        $elapsed = $this->ugcBooking(BookingStatus::Pending, ['created_at' => now()->subHours(49)]);
        $commissionPaid = $this->ugcBooking(BookingStatus::CommissionPaid, ['commission_paid_at' => now()->subHour()]);

        $this->assertTrue($this->inProcess($open, $this->producerUser));
        $this->assertFalse($this->inProcess($elapsed, $this->producerUser));
        // Commission réglée : la Face doit répondre, plus le Producteur.
        $this->assertFalse($this->inProcess($commissionPaid, $this->producerUser));
    }

    public function test_producer_service_end_to_confirm(): void
    {
        $paidShootStarted = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->subDay()->startOfDay()]);
        $paidShootFuture = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->addDays(3)->startOfDay()]);
        $confirmedByFace = $this->booking(['status' => BookingStatus::ConfirmedByFace]);
        $confirmedByProducer = $this->booking(['status' => BookingStatus::ConfirmedByProducer, 'date_debut' => now()->subDay()->startOfDay()]);

        $this->assertTrue($this->inProcess($paidShootStarted, $this->producerUser));
        $this->assertFalse($this->inProcess($paidShootFuture, $this->producerUser));
        $this->assertTrue($this->inProcess($confirmedByFace, $this->producerUser));
        // Déjà confirmé par le Producteur : l'action revient à la Face.
        $this->assertFalse($this->inProcess($confirmedByProducer, $this->producerUser));
    }

    public function test_producer_ugc_shipment_to_confirm_and_deliverable_to_validate(): void
    {
        $noShipment = $this->ugcBooking(BookingStatus::Accepted);
        $shipped = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Shipped);
        $received = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Received);
        $unboxingInReview = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::UnboxingInReview);
        $avisInReview = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::AvisInReview);
        $avisPending = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::AvisPending);
        $completedTunnel = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Completed);

        $this->assertTrue($this->inProcess($noShipment, $this->producerUser));
        $this->assertTrue($this->inProcess($unboxingInReview, $this->producerUser));
        $this->assertTrue($this->inProcess($avisInReview, $this->producerUser));
        // Colis en route, vidéo attendue de la Face, tunnel terminé : rien pour le Producteur.
        $this->assertFalse($this->inProcess($shipped, $this->producerUser));
        $this->assertFalse($this->inProcess($received, $this->producerUser));
        $this->assertFalse($this->inProcess($avisPending, $this->producerUser));
        $this->assertFalse($this->inProcess($completedTunnel, $this->producerUser));
    }

    public function test_producer_has_nothing_to_process_on_face_only_cases(): void
    {
        $cashPending = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);
        $ugcCommissionPaid = $this->ugcBooking(BookingStatus::CommissionPaid, ['commission_paid_at' => now()->subHour()]);
        $noShow = $this->booking(['status' => BookingStatus::NoShow, 'settlement_due_at' => now()->addHours(30)]);
        $this->lockEscrow($noShow);
        $confirmedByProducer = $this->booking(['status' => BookingStatus::ConfirmedByProducer]);
        $shipped = $this->withShipment($this->ugcBooking(BookingStatus::Accepted), UgcTunnelStatus::Shipped);

        foreach ([$cashPending, $ugcCommissionPaid, $noShow, $confirmedByProducer, $shipped] as $booking) {
            $this->assertFalse($this->inProcess($booking, $this->producerUser));
        }
    }

    // ------------------------------------------------------------------
    // Isolation
    // ------------------------------------------------------------------

    public function test_scope_is_isolated_between_users(): void
    {
        $face = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);
        $producer = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHour()]);

        $this->assertTrue($this->inProcess($face, $this->faceUser));
        $this->assertTrue($this->inProcess($producer, $this->producerUser));

        foreach ([$this->otherFaceUser, $this->otherProducerUser] as $stranger) {
            $this->assertFalse($this->inProcess($face, $stranger));
            $this->assertFalse($this->inProcess($producer, $stranger));
        }
    }

    public function test_user_without_face_or_producer_role_has_nothing_to_process(): void
    {
        $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);
        $stranger = User::factory()->create(['userable_type' => null, 'userable_id' => null]);

        $this->assertSame(0, Booking::query()->toProcess($stranger)->count());
    }

    // ------------------------------------------------------------------
    // API : filtre, groupes de statuts, tri par défaut
    // ------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function ids(User $viewer, string $query = ''): array
    {
        return collect(
            $this->actingAs($viewer)->getJson('/api/v1/bookings?'.$query)->assertOk()->json('data')
        )->pluck('id')->all();
    }

    public function test_status_filter_to_process_returns_only_actionable_bookings_of_the_viewer(): void
    {
        $actionable = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);
        $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(80)]);
        $this->booking(['status' => BookingStatus::Completed]);
        // Booking d'une autre Face : jamais visible.
        $this->booking([
            'status' => BookingStatus::Pending,
            'created_at' => now()->subHour(),
            'face_id' => $this->otherFaceUser->id,
        ]);

        $this->assertSame([$actionable->uuid], $this->ids($this->faceUser, 'status=to_process'));
        $this->assertSame([], $this->ids($this->producerUser, 'status=to_process'));
    }

    public function test_commission_paid_ugc_moves_from_active_to_pending_group_for_both_roles(): void
    {
        $waiting = $this->ugcBooking(BookingStatus::CommissionPaid, ['commission_paid_at' => now()->subHour()]);
        $accepted = $this->ugcBooking(BookingStatus::Accepted);

        foreach ([$this->faceUser, $this->producerUser] as $viewer) {
            $this->assertSame([$waiting->uuid], $this->ids($viewer, 'status=pending'));
            $this->assertSame([$accepted->uuid], $this->ids($viewer, 'status=active'));
        }
    }

    public function test_default_order_puts_to_process_first_then_nearest_deadline_then_updated_at(): void
    {
        // Face : trois à traiter (échéances 70 h, 10 h, aucune) puis deux autres bookings.
        $farDeadline = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(2)]);
        $nearDeadline = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHours(62)]);
        $noDeadline = $this->booking(['status' => BookingStatus::ConfirmedByProducer]);
        $recentOther = $this->booking(['status' => BookingStatus::Completed]);
        $oldOther = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => now()->addDays(5)->startOfDay()]);

        DB::table('bookings')->where('id', $noDeadline->id)->update(['updated_at' => now()->subDays(9)]);
        DB::table('bookings')->where('id', $recentOther->id)->update(['updated_at' => now()->subDay()]);
        DB::table('bookings')->where('id', $oldOther->id)->update(['updated_at' => now()->subDays(4)]);

        $this->assertSame(
            [$nearDeadline->uuid, $farDeadline->uuid, $noDeadline->uuid, $recentOther->uuid, $oldOther->uuid],
            $this->ids($this->faceUser),
        );
    }

    public function test_default_order_for_producer_uses_payment_deadline(): void
    {
        $later = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHours(2)]);
        $sooner = $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => now()->subHours(20)]);
        $other = $this->booking(['status' => BookingStatus::Completed]);
        DB::table('bookings')->where('id', $other->id)->update(['updated_at' => now()->addHour()]);

        $this->assertSame([$sooner->uuid, $later->uuid, $other->uuid], $this->ids($this->producerUser));
    }

    public function test_explicit_sort_takes_precedence_over_to_process_first(): void
    {
        $toProcess = $this->booking(['status' => BookingStatus::Pending, 'created_at' => now()->subHour()]);
        $older = $this->booking(['status' => BookingStatus::Completed, 'created_at' => now()->subDays(5)]);

        $this->assertSame([$toProcess->uuid, $older->uuid], $this->ids($this->faceUser));
        $this->assertSame(
            [$older->uuid, $toProcess->uuid],
            $this->ids($this->faceUser, 'sort=created_at&direction=asc'),
        );
    }
}
