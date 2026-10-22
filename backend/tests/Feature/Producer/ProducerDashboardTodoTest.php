<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use App\Services\ProducerDashboardTodoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProducerDashboardTodoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/producer/dashboard/todo';

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 10:00:00');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->withPersonalInfo()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function booking(array $attributes = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'type_contenu' => 'Publicité',
            'montant_total_producteur' => 110000,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function mission(array $attributes = []): Mission
    {
        return Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'titre' => 'Mission test',
            'date_tournage' => Carbon::now()->addDays(30),
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function ugcMission(array $attributes = []): array
    {
        return array_merge([
            'type_mission' => 'ugc',
            'type_compensation' => 'product',
            'status' => MissionStatus::Closed,
            'commission_paid_at' => Carbon::now()->subDays(3),
        ], $attributes);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function todo(?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->producerUser)->getJson(self::URL);
        $response->assertOk();

        return $response->json('data');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function ofType(array $items, string $type): array
    {
        return array_values(array_filter($items, fn (array $i): bool => $i['type'] === $type));
    }

    private function service(): ProducerDashboardTodoService
    {
        return app(ProducerDashboardTodoService::class);
    }

    private function paidPayment(Mission $mission, MissionPaymentStatus $status = MissionPaymentStatus::Paid): MissionPayment
    {
        return MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => $status,
            'paid_at' => $status === MissionPaymentStatus::Paid ? now() : null,
        ]);
    }

    private function paymentEntry(MissionPayment $payment, Candidature $candidature, AttendanceStatus $attendance): MissionPaymentCandidature
    {
        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $candidature->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
            'attendance_status' => $attendance,
        ]);
    }

    /**
     * @param  Booking|Candidature  $owner
     */
    private function deliverable($owner, DeliverableKind $kind, DeliverableValidationStatus $status, Carbon $submittedAt): void
    {
        $owner->deliverables()->create([
            'kind' => $kind,
            'validation_status' => $status,
            'chrono_started_at' => $submittedAt->copy()->subDay(),
            'deadline_at' => $submittedAt->copy()->addDays(6),
            'submitted_at' => $submittedAt,
            'video_path' => 'a.mp4',
            'thumbnail_path' => 'a.jpg',
            'duree_seconds' => 30,
        ]);
    }

    /**
     * @param  Booking|Candidature  $owner
     */
    private function shipment($owner, UgcTunnelStatus $status = UgcTunnelStatus::Shipped): void
    {
        $owner->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZ-'.random_int(1, 99999),
            'tunnel_status' => $status,
            'shipped_at' => Carbon::now()->subDay(),
            'destinataire_nom' => 'X',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);
    }

    // ---------------------------------------------------------------
    // Route
    // ---------------------------------------------------------------

    public function test_requires_authentication(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_forbidden_for_a_face(): void
    {
        $this->actingAs($this->faceUser)->getJson(self::URL)->assertForbidden();
    }

    public function test_empty_when_nothing_to_do(): void
    {
        $this->assertSame([], $this->todo());
    }

    public function test_returns_item_shape_without_category(): void
    {
        $this->booking([
            'status' => BookingStatus::Accepted,
            'accepted_at' => Carbon::now()->subHours(10),
        ]);

        $items = $this->todo();

        $this->assertCount(1, $items);
        $this->assertSame(
            ['action_label', 'meta', 'title', 'type', 'urgent_meta', 'url'],
            collect(array_keys($items[0]))->sort()->values()->all(),
        );
    }

    // ---------------------------------------------------------------
    // 1. Candidatures à traiter
    // ---------------------------------------------------------------

    public function test_candidatures_to_process_one_entry_per_mission_with_response_deadline(): void
    {
        // Clôture le 8/10 à 00:00 (Bénin) + 72 h => échéance 10/10 23:00 UTC, soit 37 h.
        $mission = $this->mission([
            'status' => MissionStatus::Published,
            'date_limite_candidature' => '2026-10-07',
            'date_tournage' => '2026-10-30',
        ]);
        Candidature::factory()->pending()->count(3)->create(['mission_id' => $mission->id]);
        // Pas de réponse attendue : déjà traitée
        Candidature::factory()->rejected()->create(['mission_id' => $mission->id]);
        // Autre mission, une seule candidature, sans urgence
        $other = $this->mission([
            'status' => MissionStatus::Published,
            'date_limite_candidature' => '2026-10-20',
            'date_tournage' => '2026-10-30',
        ]);
        Candidature::factory()->pending()->create(['mission_id' => $other->id]);

        $items = $this->ofType($this->todo(), 'candidatures_to_process');

        $this->assertCount(2, $items);
        $this->assertSame('3 candidatures à traiter', $items[0]['title']);
        $this->assertSame('Mission test · tournage le 30 oct.', $items[0]['meta']);
        $this->assertSame('reste 37 h', $items[0]['urgent_meta']);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $items[0]['url']);
        $this->assertSame('Traiter', $items[0]['action_label']);
        $this->assertSame('1 candidature à traiter', $items[1]['title']);
        $this->assertNull($items[1]['urgent_meta']);
    }

    public function test_candidatures_absent_for_non_published_missions_and_selection_in_payment(): void
    {
        $closed = $this->mission(['status' => MissionStatus::Closed]);
        Candidature::factory()->pending()->create(['mission_id' => $closed->id]);
        $pendingPayment = $this->mission(['status' => MissionStatus::PendingPayment]);
        Candidature::factory()->pending()->create(['mission_id' => $pendingPayment->id]);
        // Candidature publiée mais retenue dans une sélection en cours de paiement
        $published = $this->mission(['status' => MissionStatus::Published]);
        $held = Candidature::factory()->pending()->create(['mission_id' => $published->id]);
        $payment = $this->paidPayment($published, MissionPaymentStatus::Pending);
        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $held->id,
            'face_id' => $held->face_id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Pending,
        ]);

        $this->assertSame([], $this->ofType($this->todo(), 'candidatures_to_process'));
    }

    // ---------------------------------------------------------------
    // 2-3. Missions à payer
    // ---------------------------------------------------------------

    public function test_selection_payment_has_a_24h_deadline(): void
    {
        $mission = $this->mission(['status' => MissionStatus::PendingPayment]);
        $payment = $this->paidPayment($mission, MissionPaymentStatus::Pending);
        $payment->forceFill(['created_at' => Carbon::now()->subHours(10)])->saveQuietly();

        $items = $this->ofType($this->todo(), 'selection_payment');

        $this->assertCount(1, $items);
        $this->assertSame('reste 14 h', $items[0]['urgent_meta']);
        $this->assertSame('Payer', $items[0]['action_label']);
        $this->assertSame("/producer/missions/{$mission->uuid}", $items[0]['url']);
        $this->assertSame('Mission test · tournage le 8 nov.', $items[0]['meta']);
    }

    public function test_selection_payment_absent_when_mission_is_not_pending_payment(): void
    {
        $mission = $this->mission(['status' => MissionStatus::Closed]);
        $this->paidPayment($mission, MissionPaymentStatus::Pending);

        $this->assertSame([], $this->ofType($this->todo(), 'selection_payment'));
    }

    public function test_ugc_mission_commission_is_listed_without_deadline(): void
    {
        $mission = $this->mission($this->ugcMission(['status' => MissionStatus::PendingPayment, 'commission_paid_at' => null]));

        $items = $this->ofType($this->todo(), 'ugc_mission_commission');

        $this->assertCount(1, $items);
        $this->assertNull($items[0]['urgent_meta']);
        $this->assertSame("/producer/missions/{$mission->uuid}", $items[0]['url']);
        $this->assertSame([], $this->ofType($this->todo(), 'selection_payment'));
        // Mission UGC sans date de tournage : date de création
        $this->assertStringStartsWith('Mission test · ', (string) $items[0]['meta']);
    }

    // ---------------------------------------------------------------
    // 4-5. Bookings à payer
    // ---------------------------------------------------------------

    public function test_cash_booking_to_pay_within_the_24h_window(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::Accepted,
            'accepted_at' => Carbon::now()->subHours(10),
        ]);
        // Fenêtre dépassée : plus payable
        $this->booking([
            'status' => BookingStatus::Accepted,
            'accepted_at' => Carbon::now()->subHours(25),
        ]);

        $items = $this->ofType($this->todo(), 'booking_payment');

        $this->assertCount(1, $items);
        $this->assertSame('reste 14 h', $items[0]['urgent_meta']);
        $this->assertSame("/producer/bookings/{$booking->uuid}", $items[0]['url']);
        $this->assertStringContainsString($this->face->display_name, $items[0]['title']);
        $this->assertStringContainsString('110 000', $items[0]['meta']);
    }

    public function test_ugc_booking_commission_to_pay_uses_the_48h_window(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::Pending,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Sac',
            'date_debut' => null,
            'date_fin' => null,
            'created_at' => Carbon::now()->subHours(10),
        ]);
        // Cash en attente de réponse de la Face : rien à payer
        $this->booking(['status' => BookingStatus::Pending]);

        $items = $this->ofType($this->todo(), 'ugc_booking_commission');

        $this->assertCount(1, $items);
        $this->assertSame('reste 38 h', $items[0]['urgent_meta']);
        $this->assertSame("/producer/bookings/{$booking->uuid}", $items[0]['url']);
        $this->assertSame([], $this->ofType($this->todo(), 'booking_payment'));
    }

    // ---------------------------------------------------------------
    // Échéance passée : plus d'entrée (les crons horaires expirent l'élément)
    // ---------------------------------------------------------------

    public function test_entries_with_a_window_disappear_once_the_deadline_has_passed(): void
    {
        // Réponse aux candidatures : échéance 2026-10-10 23:00 UTC
        $mission = $this->mission([
            'status' => MissionStatus::Published,
            'date_limite_candidature' => '2026-10-07',
            'date_tournage' => '2026-10-30',
        ]);
        Candidature::factory()->pending()->create(['mission_id' => $mission->id]);
        // Sélection : créée il y a 10 h => échéance 2026-10-10 00:00 UTC
        $selection = $this->mission(['status' => MissionStatus::PendingPayment]);
        $this->paidPayment($selection, MissionPaymentStatus::Pending)
            ->forceFill(['created_at' => Carbon::now()->subHours(10)])->saveQuietly();
        // Commission UGC de booking : créée il y a 10 h => échéance 2026-10-11 00:00 UTC (+ 48 h)
        $this->booking([
            'status' => BookingStatus::Pending,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'created_at' => Carbon::now()->subHours(10),
        ]);
        // Booking cash à payer : accepté il y a 10 h => échéance 2026-10-10 00:00 UTC
        $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => Carbon::now()->subHours(10)]);

        $types = array_column($this->todo(), 'type');
        sort($types);
        $this->assertSame(
            ['booking_payment', 'candidatures_to_process', 'selection_payment', 'ugc_booking_commission'],
            $types,
        );

        // Après chaque échéance, l'entrée correspondante disparaît (et seulement elle)
        Carbon::setTestNow('2026-10-10 00:00:01');
        $types = array_column($this->todo(), 'type');
        sort($types);
        $this->assertSame(['candidatures_to_process', 'ugc_booking_commission'], $types);

        Carbon::setTestNow('2026-10-10 23:00:01');
        $this->assertSame(['ugc_booking_commission'], array_column($this->todo(), 'type'));

        Carbon::setTestNow('2026-10-11 00:00:01');
        $this->assertSame([], $this->todo());
    }

    public function test_mission_entries_tell_two_missions_of_the_same_title_apart(): void
    {
        foreach (['2026-11-02', '2026-11-20'] as $day) {
            $mission = $this->mission([
                'status' => MissionStatus::Published,
                'titre' => 'Shooting wax',
                'date_limite_candidature' => '2026-10-20',
                'date_tournage' => $day,
            ]);
            Candidature::factory()->pending()->create(['mission_id' => $mission->id]);
        }

        $metas = array_column($this->ofType($this->todo(), 'candidatures_to_process'), 'meta');
        sort($metas);

        // Le compte à rebours d'échéance suit la ligne mission
        $this->assertCount(2, $metas);
        $this->assertStringStartsWith('Shooting wax · tournage le 2 nov. · ', $metas[0]);
        $this->assertStringStartsWith('Shooting wax · tournage le 20 nov. · ', $metas[1]);
    }

    public function test_ugc_mission_without_shooting_date_shows_its_creation_date(): void
    {
        $mission = $this->mission($this->ugcMission(['status' => MissionStatus::PendingPayment, 'commission_paid_at' => null, 'date_tournage' => null]));
        $mission->forceFill(['created_at' => '2026-10-03 09:00:00'])->saveQuietly();

        $items = $this->ofType($this->todo(), 'ugc_mission_commission');

        $this->assertSame('Mission test · créée le 3 oct.', $items[0]['meta']);
    }

    // ---------------------------------------------------------------
    // 6. Présences
    // ---------------------------------------------------------------

    public function test_attendance_to_validate_once_the_shooting_day_has_come(): void
    {
        $mission = $this->mission(['status' => MissionStatus::Closed, 'date_tournage' => '2026-10-08']);
        $payment = $this->paidPayment($mission);
        foreach ([AttendanceStatus::Pending, AttendanceStatus::Pending, AttendanceStatus::Present] as $attendance) {
            $candidature = Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]);
            $this->paymentEntry($payment, $candidature, $attendance);
        }

        $items = $this->ofType($this->todo(), 'attendance_validation');

        $this->assertCount(1, $items);
        $this->assertSame('2 présences à valider', $items[0]['title']);
        $this->assertSame('Mission test · tournage le 8 oct.', $items[0]['meta']);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=presences", $items[0]['url']);
    }

    public function test_attendance_absent_before_shooting_day_when_all_marked_or_unpaid(): void
    {
        // Tournage dans le futur
        $future = $this->mission(['status' => MissionStatus::Closed, 'date_tournage' => '2026-10-20']);
        $this->paymentEntry($this->paidPayment($future), Candidature::factory()->confirmed()->create(['mission_id' => $future->id]), AttendanceStatus::Pending);
        // Tout est pointé
        $done = $this->mission(['status' => MissionStatus::Closed, 'date_tournage' => '2026-10-05']);
        $this->paymentEntry($this->paidPayment($done), Candidature::factory()->confirmed()->create(['mission_id' => $done->id]), AttendanceStatus::Present);
        // Paiement non confirmé
        $unpaid = $this->mission(['status' => MissionStatus::Closed, 'date_tournage' => '2026-10-05']);
        $this->paymentEntry($this->paidPayment($unpaid, MissionPaymentStatus::Pending), Candidature::factory()->confirmed()->create(['mission_id' => $unpaid->id]), AttendanceStatus::Pending);

        $this->assertSame([], $this->ofType($this->todo(), 'attendance_validation'));
    }

    // ---------------------------------------------------------------
    // 7. Livrables UGC
    // ---------------------------------------------------------------

    public function test_deliverables_in_review_one_entry_per_mission_or_booking(): void
    {
        $mission = $this->mission($this->ugcMission(['titre' => 'Appel UGC']));
        $candidature = Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]);
        $this->deliverable($candidature, DeliverableKind::Unboxing, DeliverableValidationStatus::InReview, Carbon::now()->subHours(10));
        $this->deliverable($candidature, DeliverableKind::Avis, DeliverableValidationStatus::InReview, Carbon::now()->subHours(2));
        $booking = $this->booking([
            'status' => BookingStatus::Accepted,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue',
            'date_debut' => null,
            'date_fin' => null,
        ]);
        $this->deliverable($booking, DeliverableKind::Unboxing, DeliverableValidationStatus::InReview, Carbon::now()->subHours(5));
        // Déjà validé : ignoré
        $other = Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]);
        $this->deliverable($other, DeliverableKind::Unboxing, DeliverableValidationStatus::Validated, Carbon::now()->subDay());

        $items = $this->ofType($this->todo(), 'ugc_deliverable_review');

        $this->assertCount(2, $items);
        $byUrl = array_column($items, null, 'url');
        $missionItem = $byUrl["/producer/missions/{$mission->uuid}?tab=livrables"];
        $this->assertSame('2 livrables à valider', $missionItem['title']);
        $this->assertSame('Appel UGC · tournage le 8 nov.', $missionItem['meta']);
        // Plus ancienne soumission (il y a 10 h) + 48 h => 38 h
        $this->assertSame('reste 38 h', $missionItem['urgent_meta']);
        $this->assertSame('1 livrable à valider', $byUrl["/producer/bookings/{$booking->uuid}"]['title']);
    }

    // ---------------------------------------------------------------
    // 8. Envoi du produit
    // ---------------------------------------------------------------

    public function test_shipment_to_confirm_for_ugc_booking_and_ugc_mission(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::Accepted,
            'accepted_at' => Carbon::now()->subDays(2),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue',
            'date_debut' => null,
            'date_fin' => null,
        ]);
        $mission = $this->mission($this->ugcMission());
        Candidature::factory()->confirmed()->count(2)->create(['mission_id' => $mission->id]);
        // Déjà expédiés : ignorés
        $shippedBooking = $this->booking([
            'status' => BookingStatus::Accepted,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
        ]);
        $this->shipment($shippedBooking);
        $this->shipment(Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]));

        $items = $this->ofType($this->todo(), 'ugc_shipment');

        $this->assertCount(2, $items);
        $byUrl = array_column($items, null, 'url');
        $this->assertArrayHasKey("/producer/bookings/{$booking->uuid}", $byUrl);
        $this->assertSame('2 envois de produit à confirmer', $byUrl["/producer/missions/{$mission->uuid}?tab=candidatures"]['title']);
        $this->assertSame('Confirmer', $items[0]['action_label']);
    }

    public function test_shipment_absent_when_commission_refund_requested(): void
    {
        $this->booking([
            'status' => BookingStatus::Accepted,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'commission_refund_requested_at' => Carbon::now(),
        ]);
        $mission = $this->mission($this->ugcMission(['commission_refund_requested_at' => Carbon::now()]));
        Candidature::factory()->confirmed()->create(['mission_id' => $mission->id]);

        $this->assertSame([], $this->ofType($this->todo(), 'ugc_shipment'));
    }

    // ---------------------------------------------------------------
    // 9. Fin de booking
    // ---------------------------------------------------------------

    public function test_cash_booking_past_shoot_day_to_confirm(): void
    {
        $paid = $this->booking(['status' => BookingStatus::Paid, 'date_debut' => '2026-10-07', 'date_fin' => '2026-10-08']);
        $faceConfirmed = $this->booking([
            'status' => BookingStatus::ConfirmedByFace,
            'date_debut' => '2026-10-07',
            'date_fin' => '2026-10-07',
            'face_confirmed_at' => Carbon::now()->subDay(),
        ]);
        // Exclus : déjà confirmé par le Producteur, tournage aujourd'hui, UGC
        $this->booking(['status' => BookingStatus::ConfirmedByProducer, 'date_debut' => '2026-10-07', 'date_fin' => '2026-10-07']);
        $this->booking(['status' => BookingStatus::Paid, 'date_debut' => '2026-10-09', 'date_fin' => '2026-10-09']);
        $this->booking(['status' => BookingStatus::Paid, 'type_contenu' => 'UGC', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-02']);

        $items = $this->ofType($this->todo(), 'booking_completion');

        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing(
            ["/producer/bookings/{$paid->uuid}", "/producer/bookings/{$faceConfirmed->uuid}"],
            array_column($items, 'url'),
        );
        $this->assertSame('Confirmer', $items[0]['action_label']);
    }

    // ---------------------------------------------------------------
    // 10. Noter une Face
    // ---------------------------------------------------------------

    public function test_rate_a_face_after_a_recent_booking_or_mission(): void
    {
        $booking = $this->booking(['status' => BookingStatus::Completed]);
        $mission = $this->mission(['status' => MissionStatus::Completed, 'titre' => 'Clip terminé']);
        Candidature::factory()->completed()->count(2)->create(['mission_id' => $mission->id]);

        $items = $this->ofType($this->todo(), 'rate');

        $this->assertCount(2, $items);
        $byUrl = array_column($items, null, 'url');
        $this->assertSame('Noter 2 Faces', $byUrl["/producer/missions/{$mission->uuid}?tab=candidatures"]['title']);
        $this->assertStringStartsWith('Clip terminé · tournage le ', (string) $byUrl["/producer/missions/{$mission->uuid}?tab=candidatures"]['meta']);
        $this->assertSame('Noter '.$this->face->display_name, $byUrl["/producer/bookings/{$booking->uuid}"]['title']);
        $this->assertSame('Noter', $items[0]['action_label']);
    }

    public function test_rate_absent_when_already_rated_or_older_than_7_days(): void
    {
        $rated = $this->booking(['status' => BookingStatus::Completed]);
        BookingRating::create([
            'booking_id' => $rated->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $this->faceUser->id,
            'score' => 5,
        ]);
        $old = $this->booking(['status' => BookingStatus::Completed]);
        $old->forceFill(['updated_at' => Carbon::now()->subDays(8)])->saveQuietly();

        $mission = $this->mission(['status' => MissionStatus::Completed]);
        $ratedCandidature = Candidature::factory()->completed()->create(['mission_id' => $mission->id]);
        Rating::create([
            'candidature_id' => $ratedCandidature->id,
            'rater_id' => $this->producerUser->id,
            'rated_id' => $ratedCandidature->face_id,
            'rated_type' => Face::class,
            'score' => 4,
        ]);
        Candidature::factory()->completed()->create(['mission_id' => $mission->id])
            ->forceFill(['updated_at' => Carbon::now()->subDays(8)])->saveQuietly();

        $this->assertSame([], $this->ofType($this->todo(), 'rate'));
    }

    // ---------------------------------------------------------------
    // 11. Contestation : aucune entrée Producteur
    // ---------------------------------------------------------------

    public function test_no_entry_for_no_show_or_late_cancellation_contests(): void
    {
        $this->booking([
            'status' => BookingStatus::NoShow,
            'date_debut' => Carbon::now()->subDay(),
            'date_fin' => Carbon::now()->subDay(),
            'settlement_due_at' => Carbon::now()->addHours(50),
            'disputed_at' => Carbon::now(),
        ]);
        $this->booking([
            'status' => BookingStatus::CancelledByProducer,
            'date_debut' => Carbon::now()->addDay(),
            'date_fin' => Carbon::now()->addDay(),
            'settlement_due_at' => Carbon::now()->addHours(50),
            'disputed_at' => Carbon::now(),
        ]);

        $this->assertSame([], $this->todo());
    }

    // ---------------------------------------------------------------
    // Ordre, plafond, isolation, catégories
    // ---------------------------------------------------------------

    public function test_items_sorted_by_urgency_then_type(): void
    {
        $this->booking(['status' => BookingStatus::Completed]); // rate : dernier
        $this->booking(['status' => BookingStatus::Paid, 'date_debut' => '2026-10-07', 'date_fin' => '2026-10-08']); // booking_completion
        $mission = $this->mission([
            'status' => MissionStatus::Published,
            'date_limite_candidature' => '2026-10-20',
            'date_tournage' => '2026-10-30',
        ]);
        Candidature::factory()->pending()->create(['mission_id' => $mission->id]); // candidatures, non urgent
        $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => Carbon::now()->subHours(10)]); // urgent 14 h
        $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => Carbon::now()->subHours(20)]); // urgent 4 h

        $items = $this->todo();

        $this->assertSame(
            ['booking_payment', 'booking_payment', 'candidatures_to_process', 'booking_completion', 'rate'],
            array_column($items, 'type'),
        );
        $this->assertSame('reste 4 h', $items[0]['urgent_meta']);
        $this->assertSame('reste 14 h', $items[1]['urgent_meta']);
    }

    public function test_truncated_at_eight_and_unbounded_method_carries_categories(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => Carbon::now()->subHours(1 + $i)]);
        }
        $mission = $this->mission($this->ugcMission(['status' => MissionStatus::PendingPayment, 'commission_paid_at' => null]));
        $this->deliverable(
            Candidature::factory()->confirmed()->create(['mission_id' => $this->mission($this->ugcMission())->id]),
            DeliverableKind::Unboxing,
            DeliverableValidationStatus::InReview,
            Carbon::now()->subHour(),
        );
        Candidature::factory()->pending()->create([
            'mission_id' => $this->mission([
                'status' => MissionStatus::Published,
                'date_limite_candidature' => '2026-10-20',
                'date_tournage' => '2026-10-30',
            ])->id,
        ]);
        $this->booking(['status' => BookingStatus::Completed]);

        $this->assertCount(8, $this->todo());

        $all = $this->service()->allForProducer($this->producerUser, $this->producer);
        $capped = $this->service()->forProducer($this->producerUser, $this->producer);

        // 10 bookings + commission mission + livrables + envoi (candidature confirmée sans colis) + candidatures + notation
        $this->assertCount(15, $all);
        $this->assertCount(ProducerDashboardTodoService::MAX_ITEMS, $capped);
        $this->assertSame(array_slice(array_column($all, 'type'), 0, 8), array_column($capped, 'type'));
        $this->assertArrayNotHasKey('category', $capped[0]);

        $categories = array_column($all, 'category', 'type');
        $this->assertSame('bookings', $categories['booking_payment']);
        $this->assertSame('missions', $categories['ugc_mission_commission']);
        $this->assertSame('livrables', $categories['ugc_deliverable_review']);
        $this->assertSame('candidatures', $categories['candidatures_to_process']);
        $this->assertSame('other', $categories['rate']);
        $this->assertNotNull($mission->id);
    }

    public function test_shipment_category_depends_on_booking_or_mission(): void
    {
        $this->booking([
            'status' => BookingStatus::Accepted,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
        ]);
        Candidature::factory()->confirmed()->create(['mission_id' => $this->mission($this->ugcMission())->id]);

        $all = $this->service()->allForProducer($this->producerUser, $this->producer);
        $categories = array_column($this->ofType($all, 'ugc_shipment'), 'category');
        sort($categories);

        $this->assertSame(['bookings', 'livrables'], $categories);
    }

    public function test_only_the_owning_producer_sees_the_entries(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $this->booking(['status' => BookingStatus::Accepted, 'accepted_at' => Carbon::now()->subHours(5)]);
        $mission = $this->mission(['status' => MissionStatus::Published, 'date_limite_candidature' => '2026-10-07', 'date_tournage' => '2026-10-30']);
        Candidature::factory()->pending()->create(['mission_id' => $mission->id]);
        $this->booking(['status' => BookingStatus::Completed]);

        $this->assertNotEmpty($this->todo());
        $this->assertSame([], $this->todo($otherUser));
    }
}
