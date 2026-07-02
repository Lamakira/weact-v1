<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\BookingAccepted;
use App\Events\BookingCommissionPaid;
use App\Events\BookingCreated;
use App\Events\ProductReceived;
use App\Events\UgcDeadlineApproaching;
use App\Events\UgcMissionDealAccepted;
use App\Listeners\Booking\SendBookingReceivedEmail;
use App\Listeners\Booking\SendUgcBookingAcceptedEmail;
use App\Listeners\Booking\SendUgcCommissionPaidEmail;
use App\Listeners\Mission\SendUgcMissionDealAcceptedEmail;
use App\Listeners\Ugc\SendUgcDeadlineApproachingEmail;
use App\Listeners\Ugc\SendUgcProductReceivedFaceEmail;
use App\Mail\BookingReceivedMail;
use App\Mail\UgcCommissionPaidMail;
use App\Mail\UgcDeadlineApproachingMail;
use App\Mail\UgcDealAcceptedMail;
use App\Mail\UgcProductReceivedFaceMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ugc\Concerns\BuildsUgcShipments;
use Tests\TestCase;

/**
 * UGC 7.3 — canal email ADDITIF sur les transitions à ACTION PHYSIQUE du tunnel UGC
 * (amont booking/mission + rappel deadline). Chaque listener email résout le destinataire
 * (trait ResolvesUgcOwnerRecipients) et met un Mailable en queue. Mail::fake() OBLIGATOIRE
 * (D-7.1.f : MAIL_MAILER=smtp pointe sur Gmail réel). Couvre les gardes : type_contenu='UGC'
 * sur l'event partagé BookingAccepted (D-7.3.a) et skip BookingReceivedMail pour l'UGC (D-7.3.b).
 */
class UgcActionEmailsTest extends TestCase
{
    use BuildsUgcShipments;
    use RefreshDatabase;

    private const BOOKING_FACE_EMAIL = 'face.booking@test.bj';

    private const BOOKING_PRODUCER_EMAIL = 'prod.booking@test.bj';

    private const CANDIDATURE_FACE_EMAIL = 'face.candidature@test.bj';

    private const CANDIDATURE_PRODUCER_EMAIL = 'prod.candidature@test.bj';

    // ===================================================================
    // Fixtures (calque UgcTunnelEmailsTest:67-148)
    // ===================================================================

    /**
     * Booking owner — Face/Producteur joints en direct via users.id (piège FK 2.4).
     * Le type de contenu est paramétrable pour couvrir la garde cash (D-7.3.a/b).
     */
    private function makeBookingOwner(string $typeContenu = 'UGC'): Booking
    {
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => self::BOOKING_FACE_EMAIL,
        ]);

        $producer = Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => self::BOOKING_PRODUCER_EMAIL,
        ]);

        return Booking::factory()->create([
            'face_id' => $faceUser->id,         // users.id
            'producer_id' => $producerUser->id, // users.id
            'type_contenu' => $typeContenu,
            'nom_produit' => 'Coffret Test',
        ]);
    }

    /**
     * Booking cash réel (type_contenu='Publicité', valeur du BookingFactory:56) — déclenche
     * la garde D-7.3.a (skip email « expédiez ») et la non-régression D-7.3.b (BookingReceived part).
     */
    private function makeCashBookingOwner(): Booking
    {
        return $this->makeBookingOwner('Publicité');
    }

    /**
     * Candidature owner — Face joint via faces.id, Producteur via producers.id
     * (hop userable_type/userable_id, piège FK 2.4).
     */
    private function makeCandidatureOwner(): Candidature
    {
        $producer = Producer::factory()->create(['first_name' => 'Koffi', 'last_name' => 'Mensah']);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => self::CANDIDATURE_PRODUCER_EMAIL,
        ]);

        $face = Face::factory()->create(['prenom' => 'Aïcha', 'nom' => 'Bello']);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => self::CANDIDATURE_FACE_EMAIL,
        ]);

        $mission = $this->makePaidUgcMission($producer);

        return Candidature::create([
            'face_id' => $face->id, // faces.id (PAS users.id)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);
    }

    /**
     * Calque UgcTunnelEmailsTest::makePaidUgcMission — la factory Mission ne tire
     * jamais `ugc`, attributs explicites obligatoires.
     */
    private function makePaidUgcMission(Producer $producer): Mission
    {
        return Mission::create([
            'producer_id' => $producer->id,
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief détaillé',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices lifestyle',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'ugc',
            'genre_voulu' => 'tous',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::Published,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Coffret Test',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => 2500,
        ]);
    }

    // ===================================================================
    // AC1 — BookingAccepted → Producteur « expédiez » (+ garde cash, D-7.3.a)
    // ===================================================================

    public function test_booking_accepted_queues_producer_ship_email_for_ugc(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner(); // type_contenu='UGC'

        (new SendUgcBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Mail::assertQueued(
            UgcDealAcceptedMail::class,
            fn (UgcDealAcceptedMail $m): bool => $m->hasTo(self::BOOKING_PRODUCER_EMAIL),
        );
    }

    public function test_booking_accepted_skips_email_for_cash_booking(): void
    {
        Mail::fake();
        $booking = $this->makeCashBookingOwner(); // type_contenu = 'Publicité'

        (new SendUgcBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Mail::assertNothingQueued();
    }

    public function test_booking_accepted_skips_when_producer_email_is_empty(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        User::query()->whereKey($booking->producer_id)->update(['email' => '']);

        (new SendUgcBookingAcceptedEmail)->handle(
            new BookingAccepted(Booking::query()->findOrFail($booking->id))
        );

        Mail::assertNothingQueued();
    }

    // ===================================================================
    // AC2 — UgcMissionDealAccepted → Producteur « expédiez »
    // ===================================================================

    public function test_mission_deal_accepted_queues_producer_ship_email(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();

        (new SendUgcMissionDealAcceptedEmail)->handle(new UgcMissionDealAccepted($candidature));

        Mail::assertQueued(
            UgcDealAcceptedMail::class,
            fn (UgcDealAcceptedMail $m): bool => $m->hasTo(self::CANDIDATURE_PRODUCER_EMAIL),
        );
    }

    public function test_mission_deal_accepted_skips_when_mission_is_null(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $candidature->setRelation('mission', null);

        (new SendUgcMissionDealAcceptedEmail)->handle(new UgcMissionDealAccepted($candidature));

        Mail::assertNothingQueued();
    }

    // ===================================================================
    // AC3 — BookingCommissionPaid → Face « accepte le deal »
    // ===================================================================

    public function test_commission_paid_queues_face_accept_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();

        (new SendUgcCommissionPaidEmail)->handle(new BookingCommissionPaid($booking));

        Mail::assertQueued(
            UgcCommissionPaidMail::class,
            fn (UgcCommissionPaidMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL),
        );
    }

    // ===================================================================
    // AC4 — ProductReceived → Face « filme ton Unboxing » (additif, D-7.3.c)
    // ===================================================================

    public function test_product_received_queues_face_film_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);

        (new SendUgcProductReceivedFaceEmail)->handle(new ProductReceived($shipment));

        Mail::assertQueued(
            UgcProductReceivedFaceMail::class,
            fn (UgcProductReceivedFaceMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL),
        );
    }

    public function test_product_received_queues_face_film_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $shipment = $this->makeShippedShipment($candidature);

        (new SendUgcProductReceivedFaceEmail)->handle(new ProductReceived($shipment));

        Mail::assertQueued(
            UgcProductReceivedFaceMail::class,
            fn (UgcProductReceivedFaceMail $m): bool => $m->hasTo(self::CANDIDATURE_FACE_EMAIL),
        );
    }

    // ===================================================================
    // AC5 — UgcDeadlineApproaching → Face « échéance proche » (D-7.3.d)
    // ===================================================================

    public function test_deadline_approaching_queues_face_email(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);
        // chronoWindowFor exige tunnel_status=Received + recu_le (UgcDeadlineService:71-77) :
        $shipment->update(['tunnel_status' => UgcTunnelStatus::Received, 'recu_le' => now()->subDays(5)]);

        (new SendUgcDeadlineApproachingEmail(app(UgcDeadlineService::class)))
            ->handle(new UgcDeadlineApproaching($shipment->fresh(), 2));

        Mail::assertQueued(
            UgcDeadlineApproachingMail::class,
            fn (UgcDeadlineApproachingMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL)
                && $m->level === 2
                && $m->kindLabel === 'Unboxing'
                && $m->remaining !== '',
        );
    }

    public function test_deadline_approaching_skips_when_chrono_window_is_null(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        // Shipment encore `shipped` (pas Received) ⇒ chronoWindowFor renvoie null ⇒ skip.
        $shipment = $this->makeShippedShipment($booking);

        (new SendUgcDeadlineApproachingEmail(app(UgcDeadlineService::class)))
            ->handle(new UgcDeadlineApproaching($shipment, 2));

        Mail::assertNothingQueued();
    }

    // ===================================================================
    // AC6 — Garde BookingReceivedMail : skip UGC, non-régression cash (D-7.3.b)
    // ===================================================================

    public function test_booking_created_skips_received_email_for_ugc(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner(); // UGC

        (new SendBookingReceivedEmail)->handle(new BookingCreated($booking));

        Mail::assertNothingQueued();
    }

    public function test_booking_created_still_sends_received_email_for_cash(): void
    {
        Mail::fake();
        $booking = $this->makeCashBookingOwner(); // type_contenu = 'Publicité'

        (new SendBookingReceivedEmail)->handle(new BookingCreated($booking));

        Mail::assertQueued(BookingReceivedMail::class);
    }

    // ===================================================================
    // AC7 — Smoke : les 4 nouvelles vues Blade compilent et rendent
    // ===================================================================

    public function test_all_four_mailables_render_their_blade_views(): void
    {
        $url = 'https://app.weact.test/face/bookings/abc-123';

        $mailables = [
            new UgcDealAcceptedMail('Jean Dupont', 'Awa', 'Coffret Test', $url),
            new UgcCommissionPaidMail('Awa', 'Jean Dupont', 'Coffret Test', $url),
            new UgcProductReceivedFaceMail('Awa', 'Coffret Test', $url),
            new UgcDeadlineApproachingMail('Awa', 'Coffret Test', 'Unboxing', '2 jours', 2, $url),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();

            $this->assertStringContainsString('Coffret Test', $html);
            $this->assertStringContainsString($url, $html);
            $this->assertStringContainsString('WEACT', $html); // layout de base rendu
        }
    }
}
