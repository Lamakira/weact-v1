<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Events\DeliverableRejected;
use App\Events\DeliverableRetoucheRequested;
use App\Events\DeliverableUploaded;
use App\Events\DeliverableValidated;
use App\Events\ProductReceived;
use App\Events\ShipmentConfirmed;
use App\Listeners\Ugc\SendUgcDeliverableRejectedEmail;
use App\Listeners\Ugc\SendUgcDeliverableRetoucheRequestedEmail;
use App\Listeners\Ugc\SendUgcDeliverableUploadedEmail;
use App\Listeners\Ugc\SendUgcDeliverableValidatedEmail;
use App\Listeners\Ugc\SendUgcProductReceivedEmail;
use App\Listeners\Ugc\SendUgcShipmentConfirmedEmail;
use App\Mail\UgcDeliverableRejectedMail;
use App\Mail\UgcDeliverableRetoucheRequestedMail;
use App\Mail\UgcDeliverableUploadedMail;
use App\Mail\UgcDeliverableValidatedMail;
use App\Mail\UgcProductReceivedMail;
use App\Mail\UgcShipmentConfirmedMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ugc\Concerns\BuildsUgcShipments;
use Tests\TestCase;

/**
 * UGC 7.1 — canal email ADDITIF sur les 6 events du tunnel UGC. Chaque listener
 * email résout le destinataire (asymétrie FK Booking/Candidature, trait
 * ResolvesUgcOwnerRecipients) et met un Mailable en queue. Mail::fake() OBLIGATOIRE
 * (D-7.1.f : MAIL_MAILER=smtp pointe sur Gmail réel). Couverture booking-owner ET
 * candidature-owner pour chaque event (D-7.1.g) + gardes email-vide / mission-null.
 */
class UgcTunnelEmailsTest extends TestCase
{
    use BuildsUgcShipments;
    use RefreshDatabase;

    private const BOOKING_FACE_EMAIL = 'face.booking@test.bj';

    private const BOOKING_PRODUCER_EMAIL = 'prod.booking@test.bj';

    private const CANDIDATURE_FACE_EMAIL = 'face.candidature@test.bj';

    private const CANDIDATURE_PRODUCER_EMAIL = 'prod.candidature@test.bj';

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * Booking owner — Face/Producteur joints en direct via users.id (piège FK 2.4).
     */
    private function makeBookingOwner(): Booking
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
            'type_contenu' => 'UGC',
            'nom_produit' => 'Coffret Test',
        ]);
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
     * Calque UgcMissionShipmentTest::makePaidUgcMission — la factory Mission ne
     * tire jamais `ugc`, attributs explicites obligatoires.
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

    private function makeDeliverable(Booking|Candidature $owner): Deliverable
    {
        return Deliverable::factory()->for($owner, 'owner')->create();
    }

    // ===================================================================
    // Event 1 — ShipmentConfirmed → Face
    // ===================================================================

    public function test_shipment_confirmed_queues_face_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);

        (new SendUgcShipmentConfirmedEmail)->handle(new ShipmentConfirmed($shipment));

        Mail::assertQueued(
            UgcShipmentConfirmedMail::class,
            fn (UgcShipmentConfirmedMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL)
                && $m->shipment->is($shipment),
        );
    }

    public function test_shipment_confirmed_queues_face_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $shipment = $this->makeShippedShipment($candidature);

        (new SendUgcShipmentConfirmedEmail)->handle(new ShipmentConfirmed($shipment));

        Mail::assertQueued(
            UgcShipmentConfirmedMail::class,
            fn (UgcShipmentConfirmedMail $m): bool => $m->hasTo(self::CANDIDATURE_FACE_EMAIL)
                && $m->shipment->is($shipment),
        );
    }

    // ===================================================================
    // Event 2 — ProductReceived → Producteur
    // ===================================================================

    public function test_product_received_queues_producer_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);

        (new SendUgcProductReceivedEmail)->handle(new ProductReceived($shipment));

        Mail::assertQueued(
            UgcProductReceivedMail::class,
            fn (UgcProductReceivedMail $m): bool => $m->hasTo(self::BOOKING_PRODUCER_EMAIL)
                && $m->shipment->is($shipment),
        );
    }

    public function test_product_received_queues_producer_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $shipment = $this->makeShippedShipment($candidature);

        (new SendUgcProductReceivedEmail)->handle(new ProductReceived($shipment));

        Mail::assertQueued(
            UgcProductReceivedMail::class,
            fn (UgcProductReceivedMail $m): bool => $m->hasTo(self::CANDIDATURE_PRODUCER_EMAIL)
                && $m->shipment->is($shipment),
        );
    }

    // ===================================================================
    // Event 3 — DeliverableUploaded → Producteur
    // ===================================================================

    public function test_deliverable_uploaded_queues_producer_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $deliverable = $this->makeDeliverable($booking);

        (new SendUgcDeliverableUploadedEmail)->handle(new DeliverableUploaded($deliverable));

        Mail::assertQueued(
            UgcDeliverableUploadedMail::class,
            fn (UgcDeliverableUploadedMail $m): bool => $m->hasTo(self::BOOKING_PRODUCER_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    public function test_deliverable_uploaded_queues_producer_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $deliverable = $this->makeDeliverable($candidature);

        (new SendUgcDeliverableUploadedEmail)->handle(new DeliverableUploaded($deliverable));

        Mail::assertQueued(
            UgcDeliverableUploadedMail::class,
            fn (UgcDeliverableUploadedMail $m): bool => $m->hasTo(self::CANDIDATURE_PRODUCER_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    // ===================================================================
    // Event 4 — DeliverableValidated → Face
    // ===================================================================

    public function test_deliverable_validated_queues_face_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $deliverable = $this->makeDeliverable($booking);

        (new SendUgcDeliverableValidatedEmail)->handle(new DeliverableValidated($deliverable));

        Mail::assertQueued(
            UgcDeliverableValidatedMail::class,
            fn (UgcDeliverableValidatedMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    public function test_deliverable_validated_queues_face_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $deliverable = $this->makeDeliverable($candidature);

        (new SendUgcDeliverableValidatedEmail)->handle(new DeliverableValidated($deliverable));

        Mail::assertQueued(
            UgcDeliverableValidatedMail::class,
            fn (UgcDeliverableValidatedMail $m): bool => $m->hasTo(self::CANDIDATURE_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    // ===================================================================
    // Event 5 — DeliverableRejected → Face
    // ===================================================================

    public function test_deliverable_rejected_queues_face_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $deliverable = Deliverable::factory()->rejected()->for($booking, 'owner')->create();

        (new SendUgcDeliverableRejectedEmail)->handle(new DeliverableRejected($deliverable));

        Mail::assertQueued(
            UgcDeliverableRejectedMail::class,
            fn (UgcDeliverableRejectedMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    public function test_deliverable_rejected_queues_face_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $deliverable = Deliverable::factory()->rejected()->for($candidature, 'owner')->create();

        (new SendUgcDeliverableRejectedEmail)->handle(new DeliverableRejected($deliverable));

        Mail::assertQueued(
            UgcDeliverableRejectedMail::class,
            fn (UgcDeliverableRejectedMail $m): bool => $m->hasTo(self::CANDIDATURE_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    // ===================================================================
    // Event 6 — DeliverableRetoucheRequested → Face
    // ===================================================================

    public function test_deliverable_retouche_requested_queues_face_email_for_booking_owner(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $deliverable = Deliverable::factory()->retoucheRequested()->for($booking, 'owner')->create();

        (new SendUgcDeliverableRetoucheRequestedEmail)->handle(new DeliverableRetoucheRequested($deliverable));

        Mail::assertQueued(
            UgcDeliverableRetoucheRequestedMail::class,
            fn (UgcDeliverableRetoucheRequestedMail $m): bool => $m->hasTo(self::BOOKING_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    public function test_deliverable_retouche_requested_queues_face_email_for_candidature_owner(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $deliverable = Deliverable::factory()->retoucheRequested()->for($candidature, 'owner')->create();

        (new SendUgcDeliverableRetoucheRequestedEmail)->handle(new DeliverableRetoucheRequested($deliverable));

        Mail::assertQueued(
            UgcDeliverableRetoucheRequestedMail::class,
            fn (UgcDeliverableRetoucheRequestedMail $m): bool => $m->hasTo(self::CANDIDATURE_FACE_EMAIL)
                && $m->deliverable->is($deliverable),
        );
    }

    // ===================================================================
    // Gardes — email destinataire vide ⇒ assertNothingQueued (D-7.1.d, AC6)
    // ===================================================================

    public function test_skips_when_face_email_is_empty(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $deliverable = $this->makeDeliverable($booking);
        User::query()->whereKey($booking->face_id)->update(['email' => '']);

        // Recharge le deliverable (aucune relation chargée) ⇒ owner/face relus en BDD.
        (new SendUgcDeliverableValidatedEmail)->handle(
            new DeliverableValidated(Deliverable::query()->findOrFail($deliverable->id))
        );

        Mail::assertNothingQueued();
    }

    public function test_skips_when_producer_email_is_empty(): void
    {
        Mail::fake();
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);
        User::query()->whereKey($booking->producer_id)->update(['email' => '']);

        (new SendUgcProductReceivedEmail)->handle(
            new ProductReceived(Shipment::query()->findOrFail($shipment->id))
        );

        Mail::assertNothingQueued();
    }

    // ===================================================================
    // Gardes — candidature à mission null ⇒ assertNothingQueued (D-7.1.b, AC6)
    // (mission_id est cascadeOnDelete ⇒ pas d'orphelin en BDD : on simule la
    //  relation mission=null in-memory, ce que la garde défensive doit gérer.)
    // ===================================================================

    public function test_skips_when_candidature_mission_is_null_face_recipient(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $deliverable = $this->makeDeliverable($candidature);

        $candidature->setRelation('mission', null);
        $deliverable->setRelation('owner', $candidature);

        (new SendUgcDeliverableValidatedEmail)->handle(new DeliverableValidated($deliverable));

        Mail::assertNothingQueued();
    }

    public function test_skips_when_candidature_mission_is_null_producer_recipient(): void
    {
        Mail::fake();
        $candidature = $this->makeCandidatureOwner();
        $shipment = $this->makeShippedShipment($candidature);

        $candidature->setRelation('mission', null);
        $shipment->setRelation('owner', $candidature);

        (new SendUgcProductReceivedEmail)->handle(new ProductReceived($shipment));

        Mail::assertNothingQueued();
    }

    // ===================================================================
    // Smoke — les 6 vues Blade compilent et rendent (CTA + produit présents)
    // ===================================================================

    public function test_all_six_mailables_render_their_blade_views(): void
    {
        $booking = $this->makeBookingOwner();
        $shipment = $this->makeShippedShipment($booking);
        $deliverable = Deliverable::factory()->rejected()->for($booking, 'owner')->create();
        $url = 'https://app.weact.test/face/bookings/'.$booking->uuid;

        $mailables = [
            new UgcShipmentConfirmedMail($shipment, 'Awa', 'Coffret Test', 'Jean Dupont', $url),
            new UgcProductReceivedMail($shipment, 'Jean Dupont', 'Coffret Test', $url),
            new UgcDeliverableUploadedMail($deliverable, 'Jean Dupont', 'Coffret Test', $url),
            new UgcDeliverableValidatedMail($deliverable, 'Awa', 'Coffret Test', $url),
            new UgcDeliverableRejectedMail($deliverable, 'Awa', 'Coffret Test', $url),
            new UgcDeliverableRetoucheRequestedMail($deliverable, 'Awa', 'Coffret Test', $url),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();

            $this->assertStringContainsString('Coffret Test', $html);
            $this->assertStringContainsString($url, $html);
            $this->assertStringContainsString('WEACT', $html); // layout de base rendu
        }
    }
}
