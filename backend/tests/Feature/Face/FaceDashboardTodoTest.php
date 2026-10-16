<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FaceVideo;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FaceDashboardTodoTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/face/dashboard/todo';

    private User $faceUser;

    private Face $face;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-09 10:00:00');

        // Profil complet : aucune entrée « profil » parasite dans les tests des autres types.
        $this->face = Face::factory()->withPersonalInfo()->create([
            'profile_photo' => 'photo.jpg',
            'presentation_video' => 'video.mp4',
            'bio' => 'Bio',
            'ville' => 'Cotonou',
            'categories' => ['mannequin'],
            'langues' => ['fr'],
            'tarif_journalier' => 50000,
            'whatsapp_number' => '+22990000000',
        ]);
        FaceVideo::factory()->acting()->create(['face_id' => $this->face->id]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
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
            'montant_face_recoit' => 75000,
        ], $attributes));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function todo(?User $user = null): array
    {
        $response = $this->actingAs($user ?? $this->faceUser)->getJson(self::URL);
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

    public function test_requires_authentication(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_forbidden_for_a_producer(): void
    {
        $this->actingAs($this->producerUser)->getJson(self::URL)->assertForbidden();
    }

    public function test_returns_item_shape(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addHours(22),
            'date_fin' => Carbon::now()->addHours(30),
        ]);

        $items = $this->todo();

        $this->assertCount(1, $items);
        $this->assertSame(
            ['action_label', 'meta', 'title', 'type', 'urgent_meta', 'url'],
            collect(array_keys($items[0]))->sort()->values()->all(),
        );
        $this->assertSame('booking_proposal', $items[0]['type']);
        $this->assertSame("/face/bookings/{$booking->uuid}", $items[0]['url']);
        $this->assertSame('Répondre', $items[0]['action_label']);
        $this->assertStringContainsString($this->producer->display_name, $items[0]['title']);
    }

    public function test_empty_when_nothing_to_do(): void
    {
        $this->assertSame([], $this->todo());
    }

    // ---------------------------------------------------------------
    // 1. Propositions
    // ---------------------------------------------------------------

    public function test_cash_pending_booking_urgent_under_48h(): void
    {
        $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addHours(22),
            'date_fin' => Carbon::now()->addHours(30),
        ]);

        $item = $this->ofType($this->todo(), 'booking_proposal')[0];

        $this->assertSame('expire dans 22 h', $item['urgent_meta']);
        $this->assertStringContainsString('75 000', $item['meta']);
        $this->assertStringContainsString('Publicité', $item['meta']);
    }

    public function test_cash_pending_booking_not_urgent_beyond_48h(): void
    {
        $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addDays(5),
            'date_fin' => Carbon::now()->addDays(5),
        ]);

        $item = $this->ofType($this->todo(), 'booking_proposal')[0];

        $this->assertNull($item['urgent_meta']);
        $this->assertStringContainsString('expire dans 5 j', $item['meta']);
    }

    public function test_cash_pending_booking_already_past_shoot_date_is_excluded(): void
    {
        $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->subHour(),
            'date_fin' => Carbon::now()->addDay(),
        ]);

        $this->assertSame([], $this->ofType($this->todo(), 'booking_proposal'));
    }

    public function test_ugc_commission_paid_booking_uses_acceptance_window(): void
    {
        $this->booking([
            'status' => BookingStatus::CommissionPaid,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Wax',
            'date_debut' => null,
            'date_fin' => null,
            // fenêtre 7 j : expire dans 2 j + 4 h = 52 h => pas urgent
            'commission_paid_at' => Carbon::now()->subDays(4)->subHours(20),
        ]);
        $urgent = $this->booking([
            'status' => BookingStatus::CommissionPaid,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Sac',
            'date_debut' => null,
            'date_fin' => null,
            // expire dans 10 h
            'commission_paid_at' => Carbon::now()->subDays(7)->addHours(10),
        ]);

        $items = $this->ofType($this->todo(), 'ugc_proposal');

        $this->assertCount(2, $items);
        // l'urgent d'abord
        $this->assertSame("/face/bookings/{$urgent->uuid}", $items[0]['url']);
        $this->assertSame('expire dans 10 h', $items[0]['urgent_meta']);
        $this->assertNull($items[1]['urgent_meta']);
    }

    public function test_ugc_booking_refunded_or_past_window_is_excluded(): void
    {
        $this->booking([
            'status' => BookingStatus::CommissionPaid,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'commission_paid_at' => Carbon::now()->subDays(8),
        ]);
        $this->booking([
            'status' => BookingStatus::CommissionPaid,
            'type_contenu' => 'UGC',
            'date_debut' => null,
            'date_fin' => null,
            'commission_paid_at' => Carbon::now()->subDay(),
            'commission_refunded_at' => Carbon::now(),
        ]);

        $this->assertSame([], $this->ofType($this->todo(), 'ugc_proposal'));
    }

    // ---------------------------------------------------------------
    // 2. Absence contestable
    // ---------------------------------------------------------------

    public function test_no_show_with_open_contest_window_is_urgent(): void
    {
        $booking = $this->booking([
            'status' => BookingStatus::NoShow,
            'date_debut' => Carbon::now()->subDay(),
            'date_fin' => Carbon::now()->subDay(),
            'settlement_due_at' => Carbon::now()->addHours(60),
        ]);

        $items = $this->ofType($this->todo(), 'no_show_contest');

        $this->assertCount(1, $items);
        $this->assertSame("/face/bookings/{$booking->uuid}", $items[0]['url']);
        $this->assertSame('Contester', $items[0]['action_label']);
        // 2026-10-09 10:00 UTC + 60 h = 2026-10-11 22:00 UTC = 23:00 Africa/Porto-Novo
        $this->assertSame('Contester avant le 11/10 à 23:00', $items[0]['urgent_meta']);
    }

    public function test_no_show_closed_disputed_or_expired_is_excluded(): void
    {
        $base = [
            'status' => BookingStatus::NoShow,
            'date_debut' => Carbon::now()->subDay(),
            'date_fin' => Carbon::now()->subDay(),
        ];
        $this->booking($base + ['settlement_due_at' => Carbon::now()->subHour()]);
        $this->booking($base + ['settlement_due_at' => Carbon::now()->addDay(), 'disputed_at' => Carbon::now()]);
        $this->booking($base + ['settlement_due_at' => Carbon::now()->addDay(), 'dispute_resolved_at' => Carbon::now()]);

        $this->assertSame([], $this->ofType($this->todo(), 'no_show_contest'));
    }

    // ---------------------------------------------------------------
    // 3. Confirmer la prestation
    // ---------------------------------------------------------------

    public function test_paid_booking_past_shoot_day_not_confirmed_by_face(): void
    {
        $due = $this->booking([
            'status' => BookingStatus::Paid,
            'date_debut' => '2026-10-07',
            'date_fin' => '2026-10-08',
        ]);
        $producerConfirmed = $this->booking([
            'status' => BookingStatus::ConfirmedByProducer,
            'date_debut' => '2026-10-07',
            'date_fin' => '2026-10-07',
        ]);
        // Exclus : jour de tournage = aujourd'hui, déjà confirmé par la Face, UGC
        $this->booking(['status' => BookingStatus::Paid, 'date_debut' => '2026-10-09', 'date_fin' => '2026-10-09']);
        $this->booking([
            'status' => BookingStatus::ConfirmedByFace,
            'date_debut' => '2026-10-07',
            'date_fin' => '2026-10-07',
            'face_confirmed_at' => Carbon::now()->subDay(),
        ]);
        $this->booking(['status' => BookingStatus::Paid, 'type_contenu' => 'UGC', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-02']);

        $items = $this->ofType($this->todo(), 'confirm_prestation');

        $this->assertCount(2, $items);
        $urls = array_column($items, 'url');
        $this->assertContains("/face/bookings/{$due->uuid}", $urls);
        $this->assertContains("/face/bookings/{$producerConfirmed->uuid}", $urls);
        $this->assertSame('Confirmer', $items[0]['action_label']);
        $this->assertNull($items[0]['urgent_meta']);
    }

    // ---------------------------------------------------------------
    // 4. Tunnel UGC
    // ---------------------------------------------------------------

    private function ugcBookingWithShipment(UgcTunnelStatus $status, ?Carbon $recuLe): Booking
    {
        $booking = $this->booking([
            'status' => BookingStatus::Accepted,
            'accepted_at' => Carbon::now()->subDays(10),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Wax',
            'date_debut' => null,
            'date_fin' => null,
            'commission_paid_at' => Carbon::now()->subDays(10),
        ]);
        $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZ-1',
            'tunnel_status' => $status,
            'shipped_at' => Carbon::now()->subDays(8),
            'recu_le' => $recuLe,
            'destinataire_nom' => 'X',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return $booking;
    }

    public function test_ugc_unboxing_deadline_urgent_under_48h(): void
    {
        // reçu il y a 6 j + 2 h => échéance dans 22 h
        $booking = $this->ugcBookingWithShipment(UgcTunnelStatus::Received, Carbon::now()->subDays(6)->subHours(2));

        $items = $this->ofType($this->todo(), 'ugc_deliverable');

        $this->assertCount(1, $items);
        $this->assertSame("/face/bookings/{$booking->uuid}", $items[0]['url']);
        $this->assertStringContainsString('Unboxing', $items[0]['title']);
        $this->assertSame('reste 22 h', $items[0]['urgent_meta']);
    }

    public function test_ugc_unboxing_deadline_not_urgent_when_far(): void
    {
        $this->ugcBookingWithShipment(UgcTunnelStatus::Received, Carbon::now()->subDay());

        $item = $this->ofType($this->todo(), 'ugc_deliverable')[0];

        $this->assertNull($item['urgent_meta']);
        $this->assertStringContainsString('reste 6 j', $item['meta']);
    }

    public function test_ugc_avis_deadline_comes_from_validated_unboxing(): void
    {
        $booking = $this->ugcBookingWithShipment(UgcTunnelStatus::AvisPending, Carbon::now()->subDays(15));
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => Carbon::now()->subDays(15),
            'deadline_at' => Carbon::now()->subDays(8),
            'submitted_at' => Carbon::now()->subDays(14),
            'validated_at' => Carbon::now()->subDays(13)->subHours(5), // + 14 j => dans 19 h
            'video_path' => 'a.mp4',
            'thumbnail_path' => 'a.jpg',
            'duree_seconds' => 30,
        ]);

        $item = $this->ofType($this->todo(), 'ugc_deliverable')[0];

        $this->assertStringContainsString('Avis', $item['title']);
        $this->assertSame('reste 19 h', $item['urgent_meta']);
    }

    public function test_ugc_candidature_tunnel_links_to_mission(): void
    {
        $mission = Mission::factory()->published()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Appel UGC sneakers',
        ]);
        $candidature = Candidature::factory()->confirmed()->create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
        ]);
        $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZ-2',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => Carbon::now()->subDays(3),
            'recu_le' => Carbon::now()->subDays(6),
            'destinataire_nom' => 'X',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        $item = $this->ofType($this->todo(), 'ugc_deliverable')[0];

        $this->assertSame("/face/missions/{$mission->uuid}", $item['url']);
        $this->assertSame('reste 24 h', $item['urgent_meta']);
    }

    public function test_ugc_tunnel_in_other_states_is_ignored(): void
    {
        $this->ugcBookingWithShipment(UgcTunnelStatus::Shipped, null);
        $this->ugcBookingWithShipment(UgcTunnelStatus::UnboxingInReview, Carbon::now()->subDay());
        $this->ugcBookingWithShipment(UgcTunnelStatus::Overdue, Carbon::now()->subDays(9));

        $this->assertSame([], $this->ofType($this->todo(), 'ugc_deliverable'));
    }

    // ---------------------------------------------------------------
    // 5. Candidatures sans réponse
    // ---------------------------------------------------------------

    public function test_pending_candidatures_older_than_five_days_are_grouped(): void
    {
        $missionA = Mission::factory()->published()->create(['titre' => 'Clip Gbêto']);
        $missionB = Mission::factory()->published()->create(['titre' => 'Défilé Vodun']);
        foreach ([$missionA, $missionB] as $mission) {
            Candidature::factory()->pending()->create([
                'face_id' => $this->face->id,
                'mission_id' => $mission->id,
                'created_at' => Carbon::now()->subDays(6),
            ]);
        }
        // Trop récente, déjà traitée, mission non publiée : ignorées
        Candidature::factory()->pending()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->published()->create()->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);
        Candidature::factory()->accepted()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->published()->create()->id,
            'created_at' => Carbon::now()->subDays(9),
        ]);
        Candidature::factory()->pending()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->closed()->create()->id,
            'created_at' => Carbon::now()->subDays(9),
        ]);

        $items = $this->ofType($this->todo(), 'pending_candidatures');

        $this->assertCount(1, $items);
        $this->assertSame('2 candidatures sans réponse depuis plus de 5 jours', $items[0]['title']);
        $this->assertStringContainsString('Clip Gbêto', $items[0]['meta']);
        $this->assertStringContainsString('Défilé Vodun', $items[0]['meta']);
        $this->assertSame('Voir', $items[0]['action_label']);
        $this->assertSame('/face/candidatures', $items[0]['url']);
        $this->assertNull($items[0]['urgent_meta']);
    }

    public function test_single_pending_candidature_uses_singular(): void
    {
        Candidature::factory()->pending()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->published()->create()->id,
            'created_at' => Carbon::now()->subDays(6),
        ]);

        $item = $this->ofType($this->todo(), 'pending_candidatures')[0];

        $this->assertSame('1 candidature sans réponse depuis plus de 5 jours', $item['title']);
    }

    // ---------------------------------------------------------------
    // 6. Profil
    // ---------------------------------------------------------------

    public function test_incomplete_profile_adds_one_item(): void
    {
        $this->face->update(['bio' => null, 'ville' => null]);

        $items = $this->ofType($this->todo(), 'profile_completion');

        $this->assertCount(1, $items);
        $this->assertSame('Compléter le profil', $items[0]['title']);
        $this->assertStringContainsString('2 éléments manquants', $items[0]['meta']);
        $this->assertSame('/face/profile', $items[0]['url']);
    }

    public function test_complete_profile_has_no_profile_item(): void
    {
        $this->face->refresh();
        $this->assertSame(100, $this->face->profile_completion_percentage, 'fixture : le profil doit être complet');

        $this->assertSame([], $this->ofType($this->todo(), 'profile_completion'));
    }

    // ---------------------------------------------------------------
    // Ordre, plafond, isolation, requêtes
    // ---------------------------------------------------------------

    public function test_items_sorted_by_urgency_then_type_and_capped_at_eight(): void
    {
        $this->face->update(['bio' => null]); // profile_completion : dernier
        Candidature::factory()->pending()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->published()->create()->id,
            'created_at' => Carbon::now()->subDays(6),
        ]);
        $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addDays(10),
            'date_fin' => Carbon::now()->addDays(10),
        ]); // non urgent
        $this->booking([
            'status' => BookingStatus::NoShow,
            'date_debut' => Carbon::now()->subDay(),
            'date_fin' => Carbon::now()->subDay(),
            'settlement_due_at' => Carbon::now()->addHours(50),
        ]); // urgent, échéance +50 h
        $this->booking([
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addHours(5),
            'date_fin' => Carbon::now()->addHours(5),
        ]); // urgent, échéance +5 h

        $types = array_column($this->todo(), 'type');

        $this->assertSame(
            ['booking_proposal', 'no_show_contest', 'booking_proposal', 'pending_candidatures', 'profile_completion'],
            $types,
        );

        // plafond
        for ($i = 0; $i < 10; $i++) {
            $this->booking([
                'status' => BookingStatus::Pending,
                'date_debut' => Carbon::now()->addDays(20 + $i),
                'date_fin' => Carbon::now()->addDays(20 + $i),
            ]);
        }
        $this->assertCount(8, $this->todo());
    }

    public function test_only_own_data(): void
    {
        $otherFace = Face::factory()->create();
        $otherUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $otherFace->id]);
        $this->booking([
            'face_id' => $otherUser->id,
            'status' => BookingStatus::Pending,
            'date_debut' => Carbon::now()->addHours(5),
            'date_fin' => Carbon::now()->addHours(5),
        ]);
        Candidature::factory()->pending()->create([
            'face_id' => $otherFace->id,
            'mission_id' => Mission::factory()->published()->create()->id,
            'created_at' => Carbon::now()->subDays(9),
        ]);

        $this->assertSame([], $this->ofType($this->todo(), 'booking_proposal'));
        $this->assertSame([], $this->ofType($this->todo(), 'pending_candidatures'));
    }

    // ---------------------------------------------------------------
    // Absence mission contestable (MissionPaymentCandidature, fenêtre 72 h)
    // ---------------------------------------------------------------

    private function missionAbsenceEntry(AttendanceStatus $attendance, Carbon $notifiedAt, ?Face $face = null): MissionPaymentCandidature
    {
        $face ??= $this->face;
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
            'titre' => 'Pub Absence',
        ]);
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $candidature = Candidature::factory()->confirmed()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);

        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
            'attendance_status' => $attendance,
            'notified_at' => $notifiedAt,
        ]);
    }

    public function test_mission_absence_with_open_contest_window_is_urgent_and_links_to_the_mission(): void
    {
        $entry = $this->missionAbsenceEntry(AttendanceStatus::Absent, Carbon::now()->subHours(10));

        $items = $this->ofType($this->todo(), 'mission_absence_contest');

        $this->assertCount(1, $items);
        $this->assertSame(
            '/face/missions/'.$entry->missionPayment->mission->uuid,
            $items[0]['url'],
        );
        $this->assertSame('Contester', $items[0]['action_label']);
        $this->assertStringContainsString('Pub Absence', (string) $items[0]['title'].' '.(string) $items[0]['meta']);
        $this->assertStringStartsWith('Contester avant le ', (string) $items[0]['urgent_meta']);
        // Toujours en tête de file (rang urgent), même avec > 48 h de fenêtre restante.
        $this->assertSame('mission_absence_contest', $this->todo()[0]['type']);
    }

    public function test_mission_absence_item_disappears_after_contest(): void
    {
        $entry = $this->missionAbsenceEntry(AttendanceStatus::Absent, Carbon::now()->subHours(10));
        $this->assertCount(1, $this->ofType($this->todo(), 'mission_absence_contest'));

        $entry->update(['attendance_status' => AttendanceStatus::Disputed]);

        $this->assertSame([], $this->ofType($this->todo(), 'mission_absence_contest'));
    }

    public function test_mission_absence_item_disappears_after_the_72h_deadline(): void
    {
        $this->missionAbsenceEntry(AttendanceStatus::Absent, Carbon::now()->subHours(73));

        $this->assertSame([], $this->ofType($this->todo(), 'mission_absence_contest'));
    }

    public function test_mission_absence_of_another_face_is_not_listed(): void
    {
        $other = Face::factory()->create();
        $this->missionAbsenceEntry(AttendanceStatus::Absent, Carbon::now()->subHours(5), $other);

        $this->assertSame([], $this->ofType($this->todo(), 'mission_absence_contest'));
    }

    public function test_query_count_is_constant_with_more_items(): void
    {
        $seed = function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $this->booking([
                    'status' => BookingStatus::Pending,
                    'date_debut' => Carbon::now()->addDays(3 + $i),
                    'date_fin' => Carbon::now()->addDays(3 + $i),
                ]);
                $this->booking([
                    'status' => BookingStatus::Paid,
                    'date_debut' => '2026-10-01',
                    'date_fin' => '2026-10-02',
                ]);
                $this->ugcBookingWithShipment(UgcTunnelStatus::Received, Carbon::now()->subDays(2));
                $this->ugcBookingWithShipment(UgcTunnelStatus::AvisPending, Carbon::now()->subDays(2));
                $mission = Mission::factory()->published()->create();
                $candidature = Candidature::factory()->confirmed()->create([
                    'face_id' => $this->face->id,
                    'mission_id' => $mission->id,
                ]);
                $candidature->shipment()->create([
                    'transporteur' => 'Gozem',
                    'numero_suivi' => 'GZ-'.$i,
                    'tunnel_status' => UgcTunnelStatus::Received,
                    'shipped_at' => Carbon::now()->subDays(3),
                    'recu_le' => Carbon::now()->subDays(2),
                    'destinataire_nom' => 'X',
                    'destinataire_ville' => 'Cotonou',
                    'destinataire_pays' => 'Bénin',
                ]);
                Candidature::factory()->pending()->create([
                    'face_id' => $this->face->id,
                    'mission_id' => Mission::factory()->published()->create()->id,
                    'created_at' => Carbon::now()->subDays(8),
                ]);
            }
        };

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->faceUser)->getJson(self::URL)->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $seed(1);
        $small = $count();
        $seed(4);
        $large = $count();

        $this->assertSame($small, $large, "Requêtes : {$small} avec peu de données, {$large} avec plus (N+1).");
        $this->assertLessThanOrEqual(25, $large);
    }
}
