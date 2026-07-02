<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\MissionStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\DeliverableUploaded;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\FaceVideo;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Ugc\UgcDeliverableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * UGC 4.1 — POST /api/v1/face/shipments/{shipment}/deliverables :
 * upload du livrable Unboxing sous chrono (FR6 étape 5). Crée le Deliverable
 * À l'upload en in_review, fait avancer le tunnel `received → unboxing_in_review`,
 * notifie le Producteur (post-commit). Couvre booking-owner ET candidature-owner.
 *
 * Stratégie ffmpeg = partial-mock du service (seams getVideoDuration + storeMedia
 * stubbés ; upload réel → gardes/transition/idempotence/persistance
 * testées pour de vrai, ffmpeg jamais invoqué — calque FaceVideoTest).
 */
class UgcDeliverableUploadTest extends TestCase
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

    /**
     * Booking owner — deal reçu, chrono Unboxing actif (FK : booking.face_id = users.id).
     *
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
            // NOT NULL sans default (create_bookings_table) :
            'tarif_base' => 0,
            'montant_total_producteur' => 2500,
            'montant_face_recoit' => 0,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Received, // déjà reçu
            'shipped_at' => now()->subDays(2),
            'recu_le' => now()->subDay(),                 // chrono actif
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return [$booking, $shipment];
    }

    /**
     * Candidature owner — calque UgcMissionShipmentTest (FK : candidature.face_id = faces.id).
     *
     * @return array{0: Mission, 1: Candidature, 2: Shipment}
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
     * Partial-mock : stub UNIQUEMENT le seam ffmpeg/IO public (getVideoDuration
     * + storeMedia) ; upload reste RÉEL (ffmpeg jamais invoqué).
     */
    private function mockDeliverableService(float $duration = 42.0): MockInterface
    {
        return $this->partialMock(UgcDeliverableService::class, function (MockInterface $mock) use ($duration): void {
            $mock->shouldReceive('getVideoDuration')->andReturn($duration);
            $mock->shouldReceive('storeMedia')->andReturnUsing(
                fn (UploadedFile $video, DeliverableKind $kind): array => [
                    'video_path' => "ugc/deliverables/{$kind->value}/test.mp4",
                    'thumbnail_path' => "ugc/deliverables/{$kind->value}/thumbnails/test.jpg",
                    'duree_seconds' => (int) $duration,
                ]
            );
        });
    }

    private function fakeVideo(string $name = 'unboxing.mp4'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 5 * 1024, 'video/mp4');
    }

    /**
     * Deal en avis_pending (Unboxing validé, chrono Avis actif, AUCUNE ligne Avis
     * encore) prêt pour le premier upload Avis (4.3).
     *
     * @return array{0: Booking, 1: Shipment, 2: Deliverable}
     */
    private function makeAvisPendingBooking(): array
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $validatedAt = now()->subDay();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisPending]);
        $unboxing = $booking->deliverables()->create([
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

        return [$booking, $shipment, $unboxing];
    }

    /**
     * Symétrie candidature-owner du deal en avis_pending.
     *
     * @return array{0: Candidature, 1: Shipment, 2: Deliverable}
     */
    private function makeAvisPendingCandidature(): array
    {
        [, $candidature, $shipment] = $this->makeReceivedCandidature();
        $validatedAt = now()->subDay();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::AvisPending]);
        $unboxing = $candidature->deliverables()->create([
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

        return [$candidature, $shipment, $unboxing];
    }

    // ===================================================================
    // 4.3 — Upload Avis (fenêtre avis_pending) + re-upload après reject
    // ===================================================================

    public function test_face_uploads_avis_for_booking_from_avis_pending(): void
    {
        $this->freezeTime();
        [$booking, $shipment, $unboxing] = $this->makeAvisPendingBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('avis.mp4')])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'avis')
            ->assertJsonPath('data.validation_status', 'in_review')
            ->assertJsonPath('data.chrono_started_at', $unboxing->validated_at->toIso8601String())
            ->assertJsonPath('data.deadline_at', $unboxing->validated_at->copy()->addDays(14)->toIso8601String());

        $this->assertDatabaseHas('deliverables', [
            'owner_type' => Booking::class,
            'owner_id' => $booking->id,
            'kind' => 'avis',
            'validation_status' => 'in_review',
        ]);

        $this->assertSame(UgcTunnelStatus::AvisInReview, $shipment->fresh()->tunnel_status);
        $this->assertSame(2, Deliverable::count()); // unboxing + avis
    }

    public function test_face_uploads_avis_for_candidature_from_avis_pending(): void
    {
        $this->freezeTime();
        [$candidature, $shipment, $unboxing] = $this->makeAvisPendingCandidature();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('avis.mp4')])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'avis')
            ->assertJsonPath('data.deadline_at', $unboxing->validated_at->copy()->addDays(14)->toIso8601String());

        $this->assertDatabaseHas('deliverables', [
            'owner_type' => Candidature::class,
            'owner_id' => $candidature->id,
            'kind' => 'avis',
            'validation_status' => 'in_review',
        ]);

        $this->assertSame(UgcTunnelStatus::AvisInReview, $shipment->fresh()->tunnel_status);
    }

    public function test_reupload_unboxing_after_reject_updates_in_place(): void
    {
        // Unboxing refusé, fenêtre rouverte (tunnel received) : un re-upload met à
        // jour la ligne EN PLACE (1 seule ligne), conserve le chrono, supprime
        // l'ancien média (AC6 / D-4.3.f).
        [$booking, $shipment] = $this->makeReceivedBooking();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::Received]);
        $oldVideoPath = 'ugc/deliverables/unboxing/old.mp4';
        $oldThumbPath = 'ugc/deliverables/unboxing/thumbnails/old.jpg';
        Storage::disk('local')->put($oldVideoPath, 'old-bytes');
        Storage::disk('local')->put($oldThumbPath, 'old-thumb');

        $rejected = $booking->deliverables()->create([
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::Rejected,
            'chrono_started_at' => $shipment->recu_le,
            'deadline_at' => $shipment->recu_le->copy()->addDays(7),
            'submitted_at' => now()->subHours(3),
            'review_note' => 'À refaire : cadrage hors sujet.',
            'video_path' => $oldVideoPath,
            'thumbnail_path' => $oldThumbPath,
            'duree_seconds' => 42,
        ]);
        $conservedDeadline = $rejected->deadline_at->toIso8601String();

        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('retake.mp4')])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'unboxing')
            ->assertJsonPath('data.validation_status', 'in_review')
            ->assertJsonPath('data.review_note', null);

        // Une SEULE ligne (update-in-place, contrainte unique honorée).
        $this->assertSame(1, Deliverable::count());

        $fresh = $rejected->fresh();
        $this->assertSame(DeliverableValidationStatus::InReview, $fresh->validation_status);
        $this->assertNull($fresh->review_note);
        $this->assertNull($fresh->validated_at);
        $this->assertSame($conservedDeadline, $fresh->deadline_at->toIso8601String()); // chrono conservé
        $this->assertSame('ugc/deliverables/unboxing/test.mp4', $fresh->video_path); // média neuf (mock)

        // Ancien média supprimé du disque (post-commit).
        Storage::disk('local')->assertMissing($oldVideoPath);
        Storage::disk('local')->assertMissing($oldThumbPath);

        $this->assertSame(UgcTunnelStatus::UnboxingInReview, $shipment->fresh()->tunnel_status);
    }

    public function test_second_avis_upload_returns_already_uploaded(): void
    {
        // Avis déjà soumis (tunnel avis_in_review) : 2ᵉ POST = idempotent.
        [$candidature, $shipment, $unboxing] = $this->makeAvisPendingCandidature();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('avis.mp4')])
            ->assertCreated();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('avis-again.mp4')])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_UPLOADED');

        $this->assertSame(2, Deliverable::count()); // unboxing + 1 avis, pas de 3ᵉ
    }

    // ===================================================================
    // AC2 — Happy path : upload Unboxing sous chrono (booking + candidature)
    // ===================================================================

    public function test_face_uploads_unboxing_for_booking(): void
    {
        $this->freezeTime();
        [$booking, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated()
            ->assertJsonPath('message', 'Vidéo Unboxing déposée — en attente de validation du Producteur')
            ->assertJsonPath('data.kind', 'unboxing')
            ->assertJsonPath('data.kind_label', 'Unboxing')
            ->assertJsonPath('data.validation_status', 'in_review')
            ->assertJsonPath('data.validation_status_label', 'En attente de validation')
            ->assertJsonPath('data.duree_seconds', 42)
            ->assertJsonPath('data.chrono_started_at', $shipment->recu_le->toIso8601String())
            ->assertJsonPath('data.deadline_at', $shipment->recu_le->copy()->addDays(7)->toIso8601String());

        $this->assertDatabaseHas('deliverables', [
            'owner_type' => Booking::class,
            'owner_id' => $booking->id,
            'kind' => 'unboxing',
            'validation_status' => 'in_review',
        ]);

        $this->assertSame(UgcTunnelStatus::UnboxingInReview, $shipment->fresh()->tunnel_status);
    }

    public function test_face_uploads_unboxing_for_candidature(): void
    {
        $this->freezeTime();
        [, $candidature, $shipment] = $this->makeReceivedCandidature();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'unboxing')
            ->assertJsonPath('data.validation_status', 'in_review')
            ->assertJsonPath('data.deadline_at', $shipment->recu_le->copy()->addDays(7)->toIso8601String());

        $this->assertDatabaseHas('deliverables', [
            'owner_type' => Candidature::class,
            'owner_id' => $candidature->id,
            'kind' => 'unboxing',
            'validation_status' => 'in_review',
        ]);

        $this->assertSame(UgcTunnelStatus::UnboxingInReview, $shipment->fresh()->tunnel_status);
    }

    public function test_response_never_exposes_raw_video_path(): void
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('video_path', $data);
        $this->assertArrayNotHasKey('thumbnail_path', $data);
    }

    // ===================================================================
    // AC3 — Idempotence (un seul Unboxing, un seul fichier, un seul event)
    // ===================================================================

    public function test_second_upload_returns_already_uploaded(): void
    {
        [, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo('again.mp4')])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_UPLOADED');

        $this->assertSame(1, Deliverable::count());
        $this->assertSame(1, Notification::where('type', 'ugc_deliverable_uploaded')->count());
    }

    // ===================================================================
    // AC4 — Gardes d'état (fenêtre d'upload fermée)
    // ===================================================================

    public function test_upload_rejected_when_tunnel_not_received(): void
    {
        [, $shipment] = $this->makeReceivedBooking();
        $shipment->update(['tunnel_status' => UgcTunnelStatus::Shipped, 'recu_le' => null]);
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Deliverable::count());
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->fresh()->tunnel_status);
    }

    public function test_upload_rejected_when_recu_le_null(): void
    {
        // État défensif fabriqué : tunnel `received` mais recu_le null.
        [, $shipment] = $this->makeReceivedBooking();
        $shipment->update(['recu_le' => null]);
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Deliverable::count());
    }

    public function test_upload_rejected_on_cancelled_booking(): void
    {
        // Garde owner ré-exécutée (D-3.3.f) : un booking annulé après réception
        // ne reçoit pas de livrable.
        [$booking, $shipment] = $this->makeReceivedBooking();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Deliverable::count());
        $this->assertSame(UgcTunnelStatus::Received, $shipment->fresh()->tunnel_status);
    }

    public function test_upload_rejected_on_cancelled_candidature(): void
    {
        // Symétrie owner candidature (calque UgcMissionShipmentTest).
        [, $candidature, $shipment] = $this->makeReceivedCandidature();
        $candidature->update(['status' => CandidatureStatus::Cancelled]);
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, Deliverable::count());
    }

    public function test_upload_rejected_when_refund_requested(): void
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $booking->update(['commission_refund_requested_at' => now()]);
        $this->mockDeliverableService();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertSame(0, Deliverable::count());
    }

    public function test_upload_rejected_when_refunded_out_of_band(): void
    {
        // D-2.5.h : refund réglé hors-procédure, statut owner resté Accepted.
        [$booking, $shipment] = $this->makeReceivedBooking();
        $booking->update(['commission_refunded_at' => now()]);
        $this->mockDeliverableService();

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertStringContainsString('en cours de remboursement', (string) $response->json('error.message'));
        $this->assertSame(0, Deliverable::count());
    }

    // ===================================================================
    // AC5 — Validation fichier & média distinct du portfolio
    // ===================================================================

    public function test_upload_rejects_missing_video(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_non_video_file(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", [
                'video' => UploadedFile::fake()->create('doc.pdf', 1024, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_image_file(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", [
                'video' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_disallowed_formats(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $disallowed = [
            UploadedFile::fake()->create('clip.wmv', 1024, 'video/x-ms-wmv'),
            UploadedFile::fake()->create('clip.webm', 1024, 'video/webm'),
        ];

        foreach ($disallowed as $file) {
            $this->actingAs($this->faceUser)
                ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['video']);
        }
    }

    public function test_upload_rejects_oversized_video(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", [
                'video' => UploadedFile::fake()->create('big.mp4', 201 * 1024, 'video/mp4'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);
    }

    public function test_upload_rejects_unreadable_video(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->partialMock(UgcDeliverableService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getVideoDuration')
                ->andThrow(new \RuntimeException('ffprobe could not read the file'));
        });

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video']);

        $this->assertSame(0, Deliverable::count());
    }

    public function test_upload_does_not_touch_face_video_portfolio(): void
    {
        // Témoin média distinct (AC5/D7) : aucune ligne FaceVideo, aucun quota
        // portfolio consommé.
        [, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $this->assertSame(0, FaceVideo::count());
    }

    // ===================================================================
    // AC6 — Autorisation (propriété du deal, pas d'abonnement)
    // ===================================================================

    public function test_other_face_cannot_upload(): void
    {
        [, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertForbidden();

        $this->assertSame(0, Deliverable::count());
    }

    public function test_producer_cannot_upload(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertForbidden();

        $this->assertSame(0, Deliverable::count());
    }

    public function test_unauthenticated_cannot_upload(): void
    {
        [, $shipment] = $this->makeReceivedBooking();

        $this->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnauthorized();

        $this->assertSame(0, Deliverable::count());
    }

    public function test_engaged_face_without_subscription_can_upload(): void
    {
        // Témoin pas de gate abonnement (D-3.3.e) : la Face de fixture n'a AUCUN
        // abonnement actif — elle dépose quand même son livrable (201).
        [, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();
    }

    // ===================================================================
    // AC7 — Événement & notification Producteur (POST-COMMIT, non-fatal)
    // ===================================================================

    public function test_upload_dispatches_event(): void
    {
        Event::fake([DeliverableUploaded::class]);
        [, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        Event::assertDispatched(DeliverableUploaded::class);
    }

    public function test_event_not_dispatched_on_guard_reject(): void
    {
        Event::fake([DeliverableUploaded::class]);
        [$booking, $shipment] = $this->makeReceivedBooking();
        $booking->update(['status' => BookingStatus::CancelledByProducer]);
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertUnprocessable();

        Event::assertNotDispatched(DeliverableUploaded::class);
    }

    public function test_upload_notifies_producer_for_booking(): void
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'ugc_deliverable_uploaded')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/producer/bookings/{$booking->uuid}", data_get($notification->data, 'url'));
        $this->assertSame('unboxing', data_get($notification->data, 'kind'));
        $this->assertSame(Deliverable::firstOrFail()->uuid, data_get($notification->data, 'deliverable_id'));
        $this->assertStringContainsString('Tenue Shade Fit', (string) data_get($notification->data, 'message'));
    }

    public function test_upload_notifies_producer_for_candidature(): void
    {
        [$mission, , $shipment] = $this->makeReceivedCandidature();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'ugc_deliverable_uploaded')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame("/producer/missions/{$mission->uuid}/candidatures", data_get($notification->data, 'url'));
    }

    // ===================================================================
    // AC8 — Exposition sur le détail du deal (support frontend 4.2)
    // ===================================================================

    public function test_booking_show_exposes_deliverables(): void
    {
        [$booking, $shipment] = $this->makeReceivedBooking();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.deliverables.0.kind', 'unboxing')
            ->assertJsonPath('data.deliverables.0.validation_status', 'in_review');
    }

    public function test_face_candidatures_index_exposes_deliverables(): void
    {
        [, , $shipment] = $this->makeReceivedCandidature();
        $this->mockDeliverableService();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/shipments/{$shipment->uuid}/deliverables", ['video' => $this->fakeVideo()])
            ->assertCreated();

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->assertJsonPath('data.0.deliverables.0.kind', 'unboxing');
    }
}
