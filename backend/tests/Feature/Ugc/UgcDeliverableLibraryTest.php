<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UGC 4.7 — bibliothèque d'assets Producteur. Couvre l'endpoint de liste
 * (GET /api/v1/producer/deliverables/library : livrables VALIDÉS du Producteur
 * sur les DEUX types d'owner, asymétrie FK booking↔candidature, périmètre strict
 * Validated + non-régression inbox 5A, gardes Producteur/scope) + la route de
 * téléchargement signée (Content-Disposition: attachment, 403 sans/expirée).
 *
 * Helpers fixtures répliqués de UgcDeliverableReviewTest (privés là-bas) :
 * réplication assumée plutôt qu'extraction de trait (surfaces parallèles
 * additives, l'inbox 5A reste intacte — AC6).
 */
class UgcDeliverableLibraryTest extends TestCase
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
    // Fixtures (calque UgcDeliverableReviewTest)
    // ===================================================================

    private function makeBooking(): Booking
    {
        return Booking::create([
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
    }

    /**
     * @return array{0: \App\Models\Mission, 1: Candidature}
     */
    private function makeCandidature(): array
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

        return [$mission, $candidature];
    }

    /**
     * @param  Booking|Candidature  $owner
     */
    private function makeValidatedDeliverable(
        $owner,
        DeliverableKind $kind = DeliverableKind::Unboxing,
        ?string $thumbnailPath = 'ugc/deliverables/unboxing/thumbnails/seed.jpg',
    ): Deliverable {
        return $owner->deliverables()->create([
            'kind' => $kind,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => now()->subDays(5),
            'deadline_at' => now()->subDays(5)->addDays(7),
            'submitted_at' => now()->subDays(3),
            'validated_at' => now()->subDays(2),
            'video_path' => 'ugc/deliverables/unboxing/seed.mp4',
            'thumbnail_path' => $thumbnailPath,
            'duree_seconds' => 42,
        ]);
    }

    private function putSeedMedia(): void
    {
        Storage::disk('local')->put('ugc/deliverables/unboxing/seed.mp4', 'fake-unboxing-video');
        Storage::disk('local')->put('ugc/deliverables/unboxing/thumbnails/seed.jpg', 'fake-unboxing-thumb');
    }

    // ===================================================================
    // AC1 / AC5 / AC6 — liste des validés (booking + candidature, strict Validated)
    // ===================================================================

    public function test_producer_sees_own_validated_deliverables_across_owners(): void
    {
        $booking = $this->makeBooking();
        $this->makeValidatedDeliverable($booking); // booking validé
        [, $candidature] = $this->makeCandidature();
        $this->makeValidatedDeliverable($candidature, DeliverableKind::Avis); // candidature validée

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_resolves_face_name_and_product_name_per_owner_type(): void
    {
        $booking = $this->makeBooking();
        $this->makeValidatedDeliverable($booking);
        [, $candidature] = $this->makeCandidature();
        $this->makeValidatedDeliverable($candidature, DeliverableKind::Avis);

        $data = collect(
            $this->actingAs($this->producerUser)
                ->getJson('/api/v1/producer/deliverables/library')
                ->assertOk()
                ->json('data')
        );

        $bookingItem = $data->firstWhere('owner_type', 'booking');
        $candidatureItem = $data->firstWhere('owner_type', 'candidature');

        $this->assertNotNull($bookingItem);
        $this->assertSame('Aïcha Bello', $bookingItem['face_name']);
        $this->assertSame('Tenue Shade Fit', $bookingItem['product_name']);

        $this->assertNotNull($candidatureItem);
        $this->assertSame('Aïcha Bello', $candidatureItem['face_name']);
        $this->assertSame('Sneakers Shade Fit', $candidatureItem['product_name']);
    }

    public function test_excludes_non_validated_deliverables(): void
    {
        $booking = $this->makeBooking();
        // Un in_review + un rejected + un retouche_requested sur le même deal — aucun ne ressort.
        $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now(),
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
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_orders_by_validated_at_desc(): void
    {
        $booking = $this->makeBooking();
        $older = $this->makeValidatedDeliverable($booking);
        [, $candidature] = $this->makeCandidature();
        $newer = $this->makeValidatedDeliverable($candidature, DeliverableKind::Avis);

        $older->update(['validated_at' => now()->subDays(3)]);
        $newer->update(['validated_at' => now()->subHour()]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->uuid)
            ->assertJsonPath('data.1.id', $older->uuid);
    }

    // ===================================================================
    // AC4 — garde de propriété (Producteur scopé)
    // ===================================================================

    public function test_excludes_other_producers_deliverables(): void
    {
        // Mon livrable validé.
        $booking = $this->makeBooking();
        $this->makeValidatedDeliverable($booking);

        // Le livrable validé d'un AUTRE producteur.
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
        $this->makeValidatedDeliverable($otherBooking);

        $data = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->json('data');

        $this->assertSame('Tenue Shade Fit', $data[0]['product_name']);
    }

    public function test_face_user_is_forbidden(): void
    {
        $booking = $this->makeBooking();
        $this->makeValidatedDeliverable($booking);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertForbidden();
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/producer/deliverables/library')
            ->assertUnauthorized();
    }

    // ===================================================================
    // AC2 / AC7 — URLs signées (download + lecture inline)
    // ===================================================================

    public function test_exposes_signed_download_and_video_urls(): void
    {
        $this->putSeedMedia();
        $booking = $this->makeBooking();
        $this->makeValidatedDeliverable($booking);

        $item = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/deliverables/library')
            ->assertOk()
            ->json('data.0');

        // download_url ET video_url sont présents et signés.
        $this->assertArrayHasKey('download_url', $item);
        $this->assertArrayHasKey('video_url', $item);
        $this->assertTrue(Request::create((string) $item['download_url'])->hasValidSignature());
        $this->assertTrue(Request::create((string) $item['video_url'])->hasValidSignature());
        $this->assertTrue(Request::create((string) $item['thumbnail_url'])->hasValidSignature());
    }

    // ===================================================================
    // AC2 — route de téléchargement signée (attachment)
    // ===================================================================

    public function test_download_signed_url_returns_file_as_attachment(): void
    {
        $this->putSeedMedia();
        $booking = $this->makeBooking();
        $deliverable = $this->makeValidatedDeliverable($booking);

        $url = URL::temporarySignedRoute(
            'producer.deliverables.download',
            now()->addMinutes(30),
            ['deliverable' => $deliverable->uuid],
        );

        $response = $this->get($url);
        $response->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        // Nom de fichier lisible : ugc-<kind>-<uuid8>.mp4
        $this->assertStringContainsString('ugc-unboxing-', $disposition);
        $this->assertStringContainsString('.mp4', $disposition);
    }

    public function test_unsigned_download_url_is_rejected(): void
    {
        $this->putSeedMedia();
        $booking = $this->makeBooking();
        $deliverable = $this->makeValidatedDeliverable($booking);

        $this->get("/api/v1/producer/deliverables/{$deliverable->uuid}/download")
            ->assertForbidden();
    }

    public function test_expired_download_signature_is_rejected(): void
    {
        $this->putSeedMedia();
        $booking = $this->makeBooking();
        $deliverable = $this->makeValidatedDeliverable($booking);

        $url = URL::temporarySignedRoute(
            'producer.deliverables.download',
            now()->subMinute(), // déjà expirée
            ['deliverable' => $deliverable->uuid],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_download_missing_file_returns_404(): void
    {
        // Disque fake vide (putSeedMedia NON appelé) : le fichier référencé est absent.
        $booking = $this->makeBooking();
        $deliverable = $this->makeValidatedDeliverable($booking);

        $url = URL::temporarySignedRoute(
            'producer.deliverables.download',
            now()->addMinutes(30),
            ['deliverable' => $deliverable->uuid],
        );

        $this->get($url)->assertNotFound();
    }
}
