<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\ProductPhoto;
use App\Models\Shipment;
use App\Models\User;
use App\Services\MissionService;
use App\Services\Ugc\UgcMediaCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nettoyage des médias UGC orphelins (spec spec-ugc-media-orphan-cleanup).
 *
 * Couvre le service explicite UgcMediaCleanupService (les 3 entrées : Mission /
 * Face-user / Producer-user, fichiers + rows privés/publics, idempotence), les 3
 * chemins de hard-delete (MissionService::deleteMission + admin Face/Producer
 * nettoient tout leur sous-arbre AVANT la cascade), et la commande de
 * réconciliation du backlog ugc:purge-orphan-media (orphelins nettoyés, re-run 0,
 * photos de réception transitivement orphelines en une passe, --dry-run, owner
 * vivant intact, échec par-row → FAILURE).
 */
class UgcMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Booking/reception/deliverables = disque UGC privé (`local`) ;
        // photos de mission = disque `public`.
        Storage::fake('local');
        Storage::fake('public');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->adminToken = Admin::factory()->create()->createToken('admin-token')->plainTextToken;
    }

    // =========================================================================
    // Service : les 3 entrées
    // =========================================================================

    public function test_purge_for_mission_removes_mission_photos_and_candidature_subtree(): void
    {
        $mission = $this->makeUgcMission();
        $this->seedProductPhoto($mission, 'public', 'product', 1);

        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
        $this->seedCandidatureMedia($candidature);

        // Contrôle : un booking sans rapport doit survivre (scoping).
        $survivor = $this->makeUgcBooking();
        $survivorPhoto = $this->seedProductPhoto($survivor, 'local', 'product', 1);

        app(UgcMediaCleanupService::class)->purgeForMission($mission);

        // Mission (public) + sous-arbre candidature (privé) : rows + fichiers effacés.
        $this->assertSame(1, ProductPhoto::count()); // uniquement le survivant
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertCount(3, Storage::disk('local')->allFiles()); // survivant : original + grid + large
        Storage::disk('local')->assertExists('products/'.$survivorPhoto->filename);
    }

    public function test_purge_for_face_user_removes_bookings_and_candidatures(): void
    {
        // Booking dont cette Face est la Face (bookings.face_id = users.id).
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        // Candidature du profil Face (candidatures.face_id = faces.id).
        $mission = $this->makeUgcMission();
        $missionPhoto = $this->seedProductPhoto($mission, 'public', 'product', 1);
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
        $this->seedCandidatureMedia($candidature);

        // Contrôle : le booking d'une autre Face survit.
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);
        $otherBooking = $this->makeUgcBooking($otherFaceUser);
        $this->seedProductPhoto($otherBooking, 'local', 'product', 1);

        app(UgcMediaCleanupService::class)->purgeForFaceUser($this->faceUser);

        // Restent : la photo de mission (Producteur, publique) + le booking de l'autre Face.
        $this->assertSame(2, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        // La photo de mission appartient au Producteur, pas à la Face supprimée :
        // intacte (original + grid + large = 3 fichiers).
        $this->assertCount(3, Storage::disk('public')->allFiles());
        Storage::disk('public')->assertExists('products/'.$missionPhoto->filename);
        // Sur le disque privé, il ne reste que la photo du booking de l'autre Face.
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_purge_for_producer_user_removes_bookings_and_missions_subtree(): void
    {
        // Booking dont ce Producteur est le Producteur (bookings.producer_id = users.id).
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        // Mission du Producteur + sous-arbre candidature.
        $mission = $this->makeUgcMission();
        $this->seedProductPhoto($mission, 'public', 'product', 1);
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
        $this->seedCandidatureMedia($candidature);

        // Contrôle : la mission d'un autre Producteur survit.
        $otherProducer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);
        $otherMission = $this->makeUgcMission(MissionStatus::Published, $otherProducer);
        $otherPhoto = $this->seedProductPhoto($otherMission, 'public', 'product', 1);

        app(UgcMediaCleanupService::class)->purgeForProducerUser($this->producerUser);

        $this->assertSame(1, ProductPhoto::count()); // la photo de l'autre Producteur
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        // Photo de l'autre Producteur intacte (original + grid + large = 3 fichiers).
        $this->assertCount(3, Storage::disk('public')->allFiles());
        Storage::disk('public')->assertExists('products/'.$otherPhoto->filename);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_purge_is_idempotent_when_files_already_absent(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        // Les fichiers disparaissent sous les rows (disque vidé) : deleteFiles doit
        // ignorer les absents sans lever, et les rows sont supprimées quand même.
        Storage::fake('local');

        app(UgcMediaCleanupService::class)->purgeForFaceUser($this->faceUser);

        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
    }

    // =========================================================================
    // Les 3 chemins de hard-delete
    // =========================================================================

    public function test_delete_mission_purges_full_subtree_before_cascade(): void
    {
        $mission = $this->makeUgcMission();
        $this->seedProductPhoto($mission, 'public', 'product', 1);

        // Candidature Rejected → cancelActiveCandidatesOnDelete est un no-op (isole
        // le nettoyage média de la machinerie escrow/notification) ; son shipment /
        // livrables / photos de réception doivent quand même être purgés.
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Rejected,
        ]);
        $this->seedCandidatureMedia($candidature);

        app(MissionService::class)->deleteMission($mission);

        $this->assertDatabaseMissing('missions', ['id' => $mission->id]);
        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_admin_delete_face_purges_bookings_and_candidature_media(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        $mission = $this->makeUgcMission();
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
        $this->seedCandidatureMedia($candidature);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/faces/{$this->face->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('faces', ['id' => $this->face->id]);
        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_admin_delete_producer_purges_bookings_and_mission_subtree(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        // Mission non publiée (sinon la garde active_missions bloque la suppression).
        $mission = $this->makeUgcMission(MissionStatus::Completed);
        $this->seedProductPhoto($mission, 'public', 'product', 1);
        $candidature = Candidature::create([
            'face_id' => $this->face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);
        $this->seedCandidatureMedia($candidature);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/producers/{$this->producer->uuid}")
            ->assertOk();

        $this->assertDatabaseMissing('producers', ['id' => $this->producer->id]);
        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    // =========================================================================
    // Commande de réconciliation du backlog
    // =========================================================================

    public function test_command_cleans_orphans_including_transitive_reception_photos_and_rerun_finds_nothing(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);

        // Reproduit le trou historique : hard-delete direct de l'owner (bypass du
        // service). Le shipment devient orphelin ; ses photos de réception ne sont
        // qu'orphelines TRANSITIVEMENT (leur owner Shipment existe encore), donc une
        // passe unique doit purger le shipment d'abord puis les balayer.
        DB::table('bookings')->where('id', $booking->id)->delete();

        $this->artisan('ugc:purge-orphan-media')
            ->expectsOutputToContain('1 shipment(s), 4 product_photo(s), 2 deliverable(s) orphelin(s), 0 erreur(s).')
            ->assertExitCode(0);

        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame(0, Shipment::count());
        $this->assertSame(0, Deliverable::count());
        $this->assertCount(0, Storage::disk('local')->allFiles());

        // Re-run : plus rien à purger.
        $this->artisan('ugc:purge-orphan-media')
            ->expectsOutputToContain('0 shipment(s), 0 product_photo(s), 0 deliverable(s) orphelin(s), 0 erreur(s).')
            ->assertExitCode(0);
    }

    public function test_command_dry_run_counts_transitive_reception_photos_of_orphan_shipments(): void
    {
        // Patch revue : le dry-run ne doit pas SOUS-estimer. Les photos de réception
        // d'un shipment orphelin (orphelines seulement transitivement) doivent être
        // comptées dans le prévisionnel — même total que le run réel (4), pas 2.
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking);
        DB::table('bookings')->where('id', $booking->id)->delete();

        $this->artisan('ugc:purge-orphan-media --dry-run')
            ->expectsOutputToContain('1 shipment(s), 4 product_photo(s), 2 deliverable(s) orphelin(s), 0 erreur(s).')
            ->assertExitCode(0);

        // Dry-run : rien supprimé malgré le comptage transitif.
        $this->assertSame(4, ProductPhoto::count());
        $this->assertSame(1, Shipment::count());
        $this->assertSame(2, Deliverable::count());
    }

    public function test_command_dry_run_reports_without_deleting(): void
    {
        // Orphelins DIRECTS (sans couche transitive) → le compte dry-run est exact.
        $booking = $this->makeUgcBooking();
        $photo = $this->seedProductPhoto($booking, 'local', 'product', 1);
        $deliverable = $this->seedDeliverable($booking, DeliverableKind::Unboxing);
        $this->seedShipment($booking);
        DB::table('bookings')->where('id', $booking->id)->delete();

        $this->artisan('ugc:purge-orphan-media --dry-run')
            ->expectsOutputToContain('1 shipment(s), 1 product_photo(s), 1 deliverable(s) orphelin(s), 0 erreur(s).')
            ->assertExitCode(0);

        // Rien touché : rows + fichiers intacts.
        $this->assertSame(1, ProductPhoto::count());
        $this->assertSame(1, Shipment::count());
        $this->assertSame(1, Deliverable::count());
        Storage::disk('local')->assertExists('products/'.$photo->filename);
        Storage::disk('local')->assertExists($deliverable->video_path);
    }

    public function test_command_leaves_media_with_a_live_owner_intact(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedBookingMedia($booking); // owner bien vivant

        $this->artisan('ugc:purge-orphan-media')
            ->expectsOutputToContain('0 shipment(s), 0 product_photo(s), 0 deliverable(s) orphelin(s), 0 erreur(s).')
            ->assertExitCode(0);

        $this->assertSame(4, ProductPhoto::count());
        $this->assertSame(1, Shipment::count());
        $this->assertSame(2, Deliverable::count());
        // 4 photos * 3 fichiers + 2 livrables * 2 fichiers = 16.
        $this->assertCount(16, Storage::disk('local')->allFiles());
    }

    public function test_command_logs_and_continues_on_row_error_returning_failure(): void
    {
        $booking = $this->makeUgcBooking();
        $this->seedProductPhoto($booking, 'local', 'product', 1);
        $this->seedProductPhoto($booking, 'local', 'product', 2);
        DB::table('bookings')->where('id', $booking->id)->delete();

        // Force le premier delete à lever : la commande doit logger, compter
        // l'erreur, continuer sur la row suivante, et rendre FAILURE.
        $shouldThrow = true;
        ProductPhoto::deleting(function () use (&$shouldThrow): void {
            if ($shouldThrow) {
                $shouldThrow = false;
                throw new \RuntimeException('Forced delete failure for test');
            }
        });

        try {
            $this->artisan('ugc:purge-orphan-media')
                ->expectsOutputToContain('1 erreur(s).')
                ->assertExitCode(1);

            // Une row a échoué, l'autre a été traitée : le balayage n'a pas avorté.
            $this->assertSame(1, ProductPhoto::count());
        } finally {
            ProductPhoto::getEventDispatcher()->forget('eloquent.deleting: '.ProductPhoto::class);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeUgcMission(MissionStatus $status = MissionStatus::Published, ?Producer $producer = null): Mission
    {
        $producer ??= $this->producer;

        /** @var Mission $mission */
        $mission = $producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing '.Str::random(6),
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
            'status' => $status,
            'commission_paid_at' => now(),
            'type_compensation' => 'product',
            'nom_produit' => 'Sneakers Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
        ]);

        return $mission;
    }

    private function makeUgcBooking(?User $faceUser = null): Booking
    {
        return Booking::create([
            'face_id' => ($faceUser ?? $this->faceUser)->id, // users.id
            'producer_id' => $this->producerUser->id,        // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);
    }

    private function seedProductPhoto(Booking|Mission|Shipment $owner, string $disk, string $kind, int $position): ProductPhoto
    {
        $base = Str::uuid()->toString();

        /** @var ProductPhoto $photo */
        $photo = $owner->productPhotos()->create([
            'kind' => $kind,
            'position' => $position,
            'disk' => $disk,
            'filename' => "{$base}.jpg",
            'grid' => "{$base}.webp",
            'large' => "{$base}.webp",
        ]);

        Storage::disk($disk)->put('products/'.$photo->filename, 'original');
        Storage::disk($disk)->put('products/grid/'.$photo->grid, 'grid');
        Storage::disk($disk)->put('products/large/'.$photo->large, 'large');

        return $photo;
    }

    private function seedDeliverable(Booking|Candidature $owner, DeliverableKind $kind): Deliverable
    {
        $base = Str::uuid()->toString();
        $videoPath = "ugc/deliverables/{$kind->value}/{$base}.mp4";
        $thumbnailPath = "ugc/deliverables/{$kind->value}/thumbnails/{$base}.jpg";

        /** @var Deliverable $deliverable */
        $deliverable = $owner->deliverables()->create([
            'kind' => $kind,
            'validation_status' => DeliverableValidationStatus::Validated,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'submitted_at' => now()->subHours(2),
            'validated_at' => now()->subHour(),
            'video_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
            'duree_seconds' => 42,
        ]);

        Storage::disk('local')->put($videoPath, 'video');
        Storage::disk('local')->put($thumbnailPath, 'thumb');

        return $deliverable;
    }

    private function seedShipment(Booking|Candidature $owner): Shipment
    {
        /** @var Shipment $shipment */
        $shipment = $owner->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-'.Str::upper(Str::random(8)),
            'tunnel_status' => UgcTunnelStatus::Completed,
            'shipped_at' => now()->subDays(3),
            'recu_le' => now()->subDays(2),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return $shipment;
    }

    /**
     * Booking complet : 2 photos produit (privé) + shipment + 2 photos de
     * réception (privé) + 2 livrables (unboxing + avis).
     */
    private function seedBookingMedia(Booking $booking): void
    {
        $this->seedProductPhoto($booking, 'local', 'product', 1);
        $this->seedProductPhoto($booking, 'local', 'product', 2);
        $shipment = $this->seedShipment($booking);
        $this->seedProductPhoto($shipment, 'local', 'reception', 1);
        $this->seedProductPhoto($shipment, 'local', 'reception', 2);
        $this->seedDeliverable($booking, DeliverableKind::Unboxing);
        $this->seedDeliverable($booking, DeliverableKind::Avis);
    }

    /**
     * Candidature complète : shipment + 2 photos de réception (privé) + 2
     * livrables (unboxing + avis). Une candidature ne porte pas de photo produit.
     */
    private function seedCandidatureMedia(Candidature $candidature): void
    {
        $shipment = $this->seedShipment($candidature);
        $this->seedProductPhoto($shipment, 'local', 'reception', 1);
        $this->seedProductPhoto($shipment, 'local', 'reception', 2);
        $this->seedDeliverable($candidature, DeliverableKind::Unboxing);
        $this->seedDeliverable($candidature, DeliverableKind::Avis);
    }
}
