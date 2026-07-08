<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\BookingStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\ProductReceived;
use App\Jobs\GenerateImageVariants;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Ugc\UgcShipmentService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\Feature\Ugc\Concerns\BuildsUgcShipments;
use Tests\TestCase;

/**
 * Photos de réception UGC côté Face (spec réception) : 1-2 photos OBLIGATOIRES
 * à la confirmation de réception, attachées au Shipment (kind='reception') sur
 * le disque privé, dans la transaction de markReceived. Réutilise le socle
 * spec A (product_photos, ProductPhotoService, streaming signé, pipeline).
 */
class UgcReceptionPhotosTest extends TestCase
{
    use BuildsUgcShipments;
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Photos de réception TOUJOURS sur le disque UGC privé (`local`).
        Storage::fake('local');

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
    }

    private function makeUgcBooking(): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'date_debut' => null,
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

    private function shippedShipment(): Shipment
    {
        return $this->makeShippedShipment($this->makeUgcBooking());
    }

    private function confirmUrl(Shipment $shipment): string
    {
        return "/api/v1/face/shipments/{$shipment->uuid}/confirm-receipt";
    }

    // =========================================================================
    // Happy path : stockage privé `reception`, jobs variantes, URLs signées
    // =========================================================================

    public function test_confirm_receipt_stores_private_reception_rows_and_queues_jobs(): void
    {
        Queue::fake();

        $shipment = $this->shippedShipment();

        $response = $this->actingAs($this->faceUser)
            ->post($this->confirmUrl($shipment), $this->receiptPhotos(2), ['Accept' => 'application/json'])
            ->assertOk();

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Received, $shipment->tunnel_status);

        $photos = $shipment->receptionPhotos()->get();
        $this->assertCount(2, $photos);
        $this->assertSame([1, 2], $photos->pluck('position')->all());
        $this->assertSame(['local', 'local'], $photos->pluck('disk')->all());
        $this->assertSame(['reception', 'reception'], $photos->pluck('kind')->all());

        foreach ($photos as $photo) {
            Storage::disk('local')->assertExists('products/'.$photo->filename);
            $this->assertNull($photo->grid);
            $this->assertNull($photo->large);
        }

        // Variantes grid+large via le MÊME job que les photos produit (catalogue inchangé).
        Queue::assertPushed(GenerateImageVariants::class, 2);
        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_PRODUCT_PHOTO
        );

        // La réponse expose les photos de réception avec des URLs SIGNÉES — jamais public.
        $items = $response->json('data.reception_photos');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertTrue(HttpRequest::create((string) $item['photo_url'])->hasValidSignature());
            $this->assertTrue(HttpRequest::create((string) $item['grid_url'])->hasValidSignature());
            $this->assertStringContainsString('/product-photos/', (string) $item['grid_url']);
            $this->assertStringNotContainsString('/storage/', (string) $item['photo_url']);
        }
    }

    // =========================================================================
    // Validation (422 français, statut inchangé, rien persisté)
    // =========================================================================

    public function test_confirm_receipt_without_photos_is_rejected(): void
    {
        $shipment = $this->shippedShipment();

        $this->actingAs($this->faceUser)
            ->postJson($this->confirmUrl($shipment)) // payload vide → FormRequest rejette
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reception_photos' => 'Au moins une photo du produit reçu est requise.',
            ]);

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->tunnel_status);
        $this->assertNull($shipment->recu_le);
        $this->assertSame(0, $shipment->receptionPhotos()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_non_owner_face_is_denied_before_validation_even_without_photos(): void
    {
        // Patch revue : l'ownership (403) précède la validation des photos (422).
        // Une Face NON propriétaire postant SANS photo reçoit 403 (pas 422) —
        // elle n'apprend pas le contrat photos et rien n'est persisté.
        $shipment = $this->shippedShipment();

        $otherFace = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);

        $this->actingAs($otherFace)
            ->postJson($this->confirmUrl($shipment)) // aucun payload
            ->assertForbidden();

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->tunnel_status);
        $this->assertNull($shipment->recu_le);
        $this->assertSame(0, $shipment->receptionPhotos()->count());
    }

    public function test_three_reception_photos_are_rejected(): void
    {
        $shipment = $this->shippedShipment();

        $this->actingAs($this->faceUser)
            ->post($this->confirmUrl($shipment), $this->receiptPhotos(3), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reception_photos.0', 'Vous ne pouvez joindre que 2 photos de réception.');

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->tunnel_status);
        $this->assertSame(0, $shipment->receptionPhotos()->count());
    }

    public function test_pdf_reception_photo_is_rejected_with_french_message(): void
    {
        $shipment = $this->shippedShipment();

        $response = $this->actingAs($this->faceUser)
            ->post(
                $this->confirmUrl($shipment),
                ['reception_photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')]],
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reception_photos.0']);

        $this->assertContains(
            'Chaque photo de réception doit être une image.',
            $response->json('errors')['reception_photos.0'],
        );
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->fresh()->tunnel_status);
    }

    public function test_oversized_reception_photo_is_rejected(): void
    {
        $shipment = $this->shippedShipment();

        $response = $this->actingAs($this->faceUser)
            ->post(
                $this->confirmUrl($shipment),
                ['reception_photos' => [UploadedFile::fake()->image('big.jpg')->size(9 * 1024)]], // 9 Mo > 8
                ['Accept' => 'application/json'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reception_photos.0']);

        $this->assertContains(
            'Chaque photo de réception ne doit pas dépasser 8 Mo.',
            $response->json('errors')['reception_photos.0'],
        );
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->fresh()->tunnel_status);
    }

    // =========================================================================
    // Rollback statut sur échec de stockage (pas d'event, cleanup)
    // =========================================================================

    public function test_storage_failure_rolls_back_status_and_emits_no_event(): void
    {
        Event::fake([ProductReceived::class]);

        $shipment = $this->shippedShipment();

        // putFileAs RETOURNE false (disque plein/permissions, disque `throw => false`) :
        // attach() lève → la transaction de markReceived rollback le passage
        // Shipped → Received ET le dispatch post-commit n'a pas lieu.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            app(UgcShipmentService::class)->markReceived(
                $shipment,
                [UploadedFile::fake()->image('reception.jpg')],
            );
            $this->fail('Expected a RuntimeException on a failed original write.');
        } catch (\RuntimeException) {
            // attendu
        }

        $shipment->refresh();
        $this->assertSame(UgcTunnelStatus::Shipped, $shipment->tunnel_status);
        $this->assertNull($shipment->recu_le);
        $this->assertSame(0, $shipment->receptionPhotos()->count());
        Event::assertNotDispatched(ProductReceived::class);
    }

    // =========================================================================
    // Streaming signé + exposition aux deux parties / tiers refusé
    // =========================================================================

    public function test_reception_photo_is_served_signed_and_denied_unsigned(): void
    {
        $shipment = $this->shippedShipment();

        $this->actingAs($this->faceUser)
            ->post($this->confirmUrl($shipment), $this->receiptPhotos(), ['Accept' => 'application/json'])
            ->assertOk();

        $photo = $shipment->receptionPhotos()->firstOrFail();

        $signed = URL::temporarySignedRoute(
            'product-photos.original',
            now()->addMinutes(30),
            ['productPhoto' => $photo->uuid],
        );
        $this->get($signed)->assertOk();

        // La signature EST la garde : sans signature, refus.
        $this->get('/api/v1/product-photos/'.$photo->uuid.'/original')->assertForbidden();
    }

    public function test_both_parties_see_reception_photos_and_a_stranger_is_denied(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);

        $this->actingAs($this->faceUser)
            ->post($this->confirmUrl($shipment), $this->receiptPhotos(2), ['Accept' => 'application/json'])
            ->assertOk();

        foreach ([$this->producerUser, $this->faceUser] as $party) {
            $items = $this->actingAs($party)
                ->getJson('/api/v1/bookings/'.$booking->uuid)
                ->assertOk()
                ->json('data.shipment.reception_photos');

            $this->assertCount(2, $items);
            $this->assertTrue(HttpRequest::create((string) $items[0]['photo_url'])->hasValidSignature());
        }

        $stranger = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->actingAs($stranger)
            ->getJson('/api/v1/bookings/'.$booking->uuid)
            ->assertForbidden();
    }

    // =========================================================================
    // Rétrocompat : shipment reçu pré-deploy (0 photo) — aucune régression
    // =========================================================================

    public function test_pre_deploy_received_shipment_exposes_empty_reception_photos(): void
    {
        $booking = $this->makeUgcBooking();
        $shipment = $this->makeShippedShipment($booking);
        // Réception posée AVANT le deploy : recu_le figé, aucune photo attachée.
        $shipment->update([
            'tunnel_status' => UgcTunnelStatus::Received,
            'recu_le' => now(),
        ]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/bookings/'.$booking->uuid)
            ->assertOk()
            ->assertJsonPath('data.shipment.recu_le', fn ($value) => $value !== null)
            ->assertJsonPath('data.shipment.reception_photos', []);
    }
}
