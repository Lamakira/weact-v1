<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * UGC 4.4 — inbox de validation Producteur (écran 5A). Couvre l'endpoint de
 * liste agrégée (GET /api/v1/producer/deliverables : livrables in_review du
 * Producteur sur les DEUX types d'owner, asymétrie FK booking↔candidature,
 * contexte owner résolu, ordre submitted_at, URLs média signées, gardes
 * Producteur/auth) + les 2 routes média signées (streaming disque privé,
 * signature = garde, 403 sans/expirée, 404 fichier/miniature absents).
 *
 * Helpers fixtures répliqués de UgcDeliverableValidationTest:81-271 (privés là-bas) :
 * réplication assumée plutôt qu'extraction de trait (ValidationTest reste intact).
 */
class UgcDeliverableReviewTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        Storage::fake('local');
    }

    // ===================================================================
    // Fixtures (calque UgcDeliverableValidationTest)
    // ===================================================================

    /**
     * @return array{0: Booking, 1: Shipment}
     */
    private function makeReceivedBooking(): array
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,         // users.id
            'producer_id' => $this->producerUser->id, // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now()->subDay(),
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDay(),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$booking, $shipment];
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment}
     */
    private function makeReceivedCandidature(): array
    {
        $mission = $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing sneakers',
            'description' => 'Brief',
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
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        $candidature = Candidature::create([
            'face_id' => $this->face->id,  // faces.id (PAS users.id)
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $shipment = $candidature->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDay(),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$mission, $candidature, $shipment];
    }

    /**
     * Deal en unboxing_in_review avec un Unboxing in_review prêt à valider.
     *
     * @return array{0: Booking, 1: Shipment, 2: \App\Models\Deliverable}
     */
    private function makeUnboxingInReviewBooking(): array
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $unboxing = $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);

        return [$booking, $shipment, $unboxing];
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment, 3: \App\Models\Deliverable}
     */
    private function makeUnboxingInReviewCandidature(): array
    {
        [$mission, $candidature, $shipment] = $this->makeReceivedCandidature();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $unboxing = $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);

        return [$mission, $candidature, $shipment, $unboxing];
    }

    /**
     * Deal candidature en avis_in_review : Unboxing validé + Avis in_review.
     *
     * @return array{0: \App\Models\Mission, 1: Candidature, 2: Shipment, 3: \App\Models\Deliverable}
     */
    private function makeAvisInReviewCandidature(): array
    {
        [$mission, $candidature, $shipment] = $this->makeReceivedCandidature();
        $validatedAt = now()->subHours(6);
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisInReview]);
        $candidature->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => $shipment->recu_le,
            'validated_at' => $validatedAt,
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            'duree_seconds' => 42,
        ]);
        $avis = $candidature->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => $validatedAt,
            'deadline_at' => $validatedAt->copy()->addDays(14),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/seed.jpg',
            'duree_seconds' => 88,
        ]);

        return [$mission, $candidature, $shipment, $avis];
    }

    /**
     * Pose les fichiers seed (vidéo + miniature) sur le disque privé fake, pour
     * les assertions de streaming média.
     */
    private function putSeedMedia(): void
    {
        Storage::disk('local')->put('ugc/deliverables/unboxing/seed.mp4', 'fake-unboxing-video');
        Storage::disk('local')->put('ugc/deliverables/unboxing/thumbnails/seed.jpg', 'fake-unboxing-thumb');
        Storage::disk('local')->put('ugc/deliverables/avis/seed.mp4', 'fake-avis-video');
        Storage::disk('local')->put('ugc/deliverables/avis/thumbnails/seed.jpg', 'fake-avis-thumb');
    }

    // ===================================================================
    // AC1 — endpoint de liste (booking + candidature)
    // ===================================================================

    public function test_producer_sees_own_in_review_deliverables_across_owners(): void
    {
        $this->makeUnboxingInReviewBooking();        // 1 in_review (booking)
        $this->makeAvisInReviewCandidature();         // 1 validated + 1 in_review (candidature)

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_resolves_face_name_and_product_name_per_owner_type(): void
    {
        $this->makeUnboxingInReviewBooking();
        $this->makeUnboxingInReviewCandidature();

        $data = collect(
            $this->actingAs($this->producerUser)
                ->getJson('/api/v1/producer/deliverables')
                ->assertOk()
                ->json('data')
        );

        $bookingItem = $data->firstWhere('owner_type', 'booking');
        $candidatureItem = $data->firstWhere('owner_type', 'candidature');

        // Booking : face via face.userable (User → Face), produit = booking.nom_produit.
        $this->assertNotNull($bookingItem);
        $this->assertSame('Aïcha Bello', $bookingItem['face_name']);
        $this->assertSame('Tenue Shade Fit', $bookingItem['product_name']);

        // Candidature : face directe (Face), produit = mission.nom_produit (asymétrie).
        $this->assertNotNull($candidatureItem);
        $this->assertSame('Aïcha Bello', $candidatureItem['face_name']);
        $this->assertSame('Sneakers Shade Fit', $candidatureItem['product_name']);
    }

    public function test_excludes_non_in_review_deliverables(): void
    {
        [$booking] = $this->makeReceivedBooking();
        // Un validated + un rejected sur le même deal — aucun ne doit ressortir.
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now()->subDay(),
            'validated_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 30,
        ]);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Avis,
            'validation_status' => DeliverableValidationStatus::Rejected,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(13),
            'submitted_at' => now()->subDay(),
            'review_note' => 'Refais la prise.',
            'video_path' => 'ugc/deliverables/avis/seed.mp4',
            'duree_seconds' => 50,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_excludes_other_producers_deliverables(): void
    {
        // Mon deal in_review.
        $this->makeUnboxingInReviewBooking();

        // Le deal d'un AUTRE producteur, in_review aussi.
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $otherBooking = Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $otherProducerUser->id,
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Produit concurrent',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'commission_paid_at' => now()->subDay(),
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);
        $otherBooking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'duree_seconds' => 40,
        ]);

        $data = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertSame('Tenue Shade Fit', $data[0]['product_name']);
    }

    public function test_orders_by_submitted_at(): void
    {
        [, , $older] = $this->makeUnboxingInReviewBooking();
        [, , , $newer] = $this->makeUnboxingInReviewCandidature();

        // submitted_at distincts : older posté il y a 3 h, newer il y a 1 h.
        $older->update(['submitted_at' => now()->subHours(3)]);
        $newer->update(['submitted_at' => now()->subHour()]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->uuid)
            ->assertJsonPath('data.1.id', $newer->uuid);
    }

    public function test_exposes_signed_video_and_thumbnail_urls(): void
    {
        $this->putSeedMedia();
        $this->makeUnboxingInReviewBooking();

        $item = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->json('data.0');

        // Les URLs sont signées (signature valide recalculée sur l'URL extraite).
        $this->assertTrue(Request::create((string) $item['video_url'])->hasValidSignature());
        $this->assertTrue(Request::create((string) $item['thumbnail_url'])->hasValidSignature());

        // Et elles servent réellement le média (signature acceptée par la route).
        $this->get($item['video_url'])->assertOk();
        $this->get($item['thumbnail_url'])->assertOk();
    }

    public function test_thumbnail_url_is_null_when_thumbnail_path_null(): void
    {
        [$booking] = $this->makeReceivedBooking();
        $booking->shipment->update(['tunnel_status' => UgcTunnelStatus::UnboxingInReview]);
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => null,
            'duree_seconds' => 40,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertOk()
            ->assertJsonPath('data.0.thumbnail_url', null)
            ->assertJsonPath('data.0.video_url', fn (?string $url) => is_string($url) && $url !== '');
    }

    public function test_face_user_is_forbidden(): void
    {
        $this->makeUnboxingInReviewBooking();

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/producer/deliverables')
            ->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/producer/deliverables')
            ->assertUnauthorized();
    }

    // ===================================================================
    // AC2 — routes média signées (streaming disque privé)
    // ===================================================================

    public function test_video_signed_url_returns_the_file(): void
    {
        $this->putSeedMedia();
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $url = URL::temporarySignedRoute(
            'producer.deliverables.video',
            now()->addMinutes(30),
            ['deliverable' => $unboxing->uuid],
        );

        $response = $this->get($url);
        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringEndsWith(
            'ugc/deliverables/unboxing/seed.mp4',
            $response->baseResponse->getFile()->getPathname(),
        );
    }

    public function test_thumbnail_signed_url_returns_the_file(): void
    {
        $this->putSeedMedia();
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $url = URL::temporarySignedRoute(
            'producer.deliverables.thumbnail',
            now()->addMinutes(30),
            ['deliverable' => $unboxing->uuid],
        );

        $response = $this->get($url);
        $response->assertOk();
        $this->assertStringEndsWith(
            'ugc/deliverables/unboxing/thumbnails/seed.jpg',
            $response->baseResponse->getFile()->getPathname(),
        );
    }

    public function test_unsigned_video_url_is_rejected(): void
    {
        $this->putSeedMedia();
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        // Aucune query signée → 403 (la signature EST la garde, D-4.4.c).
        $this->get("/api/v1/producer/deliverables/{$unboxing->uuid}/video")
            ->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $this->putSeedMedia();
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $url = URL::temporarySignedRoute(
            'producer.deliverables.video',
            now()->subMinute(), // déjà expirée
            ['deliverable' => $unboxing->uuid],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_null_thumbnail_returns_404(): void
    {
        [$booking] = $this->makeReceivedBooking();
        $deliverable = $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now(),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => null,
            'duree_seconds' => 40,
        ]);

        $url = URL::temporarySignedRoute(
            'producer.deliverables.thumbnail',
            now()->addMinutes(30),
            ['deliverable' => $deliverable->uuid],
        );

        $this->get($url)->assertNotFound();
    }

    public function test_missing_video_file_returns_404(): void
    {
        // Disque fake vide (putSeedMedia NON appelé) : le fichier référencé est absent.
        [, , $unboxing] = $this->makeUnboxingInReviewBooking();

        $url = URL::temporarySignedRoute(
            'producer.deliverables.video',
            now()->addMinutes(30),
            ['deliverable' => $unboxing->uuid],
        );

        $this->get($url)->assertNotFound();
    }
}
