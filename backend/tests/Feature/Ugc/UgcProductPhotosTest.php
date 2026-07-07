<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Jobs\GenerateImageVariants;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\ProductPhoto;
use App\Models\User;
use App\Services\ProductPhotoService;
use App\Support\ImageVariantGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Photos produit UGC côté Producteur (spec photos produit) : upload à la
 * création booking/mission, stockage mixte (booking = disque privé + URLs
 * signées ; mission = disque public), variantes via le pipeline chantier 1,
 * streaming signé, nettoyage à la suppression.
 */
class UgcProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Booking = disque UGC privé (`local`) ; Mission = disque `public`.
        Storage::fake('local');
        Storage::fake('public');

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create(['is_available' => true]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validUgcBookingData(): array
    {
        return [
            'face_id' => $this->face->uuid,
            'type_contenu' => 'UGC',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validUgcMissionData(): array
    {
        return [
            'titre' => 'Appel UGC — Unboxing Tenue Shade Fit',
            'description' => 'Nous cherchons des créatrices pour un unboxing + avis.',
            'profil_recherche' => 'Créatrices lifestyle, lumière naturelle',
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
        ];
    }

    // =========================================================================
    // Création booking UGC avec photos (disque privé, jobs queued, URLs signées)
    // =========================================================================

    public function test_ugc_booking_with_two_photos_stores_private_rows_and_queues_jobs(): void
    {
        Queue::fake();

        $data = $this->validUgcBookingData();
        $data['product_photos'] = [
            UploadedFile::fake()->image('photo-1.jpg', 900, 900),
            UploadedFile::fake()->image('photo-2.png', 900, 900),
        ];

        $response = $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $data, ['Accept' => 'application/json']);

        $response->assertCreated();

        $booking = Booking::firstOrFail();
        $photos = $booking->productPhotos()->get();

        $this->assertCount(2, $photos);
        $this->assertSame([1, 2], $photos->pluck('position')->all());
        $this->assertSame(['local', 'local'], $photos->pluck('disk')->all());
        $this->assertSame(['product', 'product'], $photos->pluck('kind')->all());

        // Originaux sur le disque PRIVÉ, rien sur le public, aucune variante synchrone.
        foreach ($photos as $photo) {
            Storage::disk('local')->assertExists('products/'.$photo->filename);
            $this->assertNull($photo->grid);
            $this->assertNull($photo->large);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->files('products/grid'));
        $this->assertSame([], Storage::disk('local')->files('products/large'));

        Queue::assertPushed(GenerateImageVariants::class, 2);
        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_PRODUCT_PHOTO
        );

        // La réponse expose les photos avec des URLs SIGNÉES (fallback original
        // tant que les variantes ne sont pas générées) — jamais d'asset public.
        $items = $response->json('data.product_photos');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertTrue(HttpRequest::create((string) $item['photo_url'])->hasValidSignature());
            $this->assertTrue(HttpRequest::create((string) $item['grid_url'])->hasValidSignature());
            $this->assertStringContainsString('/product-photos/', (string) $item['grid_url']);
            $this->assertStringNotContainsString('/storage/', (string) $item['photo_url']);
        }
    }

    public function test_ugc_booking_without_photos_is_unchanged(): void
    {
        $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $this->validUgcBookingData(), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_booking_show_exposes_product_photos_to_both_parties(): void
    {
        $booking = $this->createBookingWithPhoto();

        foreach ([$this->producerUser, $this->faceUser] as $party) {
            $items = $this->actingAs($party)
                ->getJson('/api/v1/bookings/'.$booking->uuid)
                ->assertOk()
                ->json('data.product_photos');

            $this->assertCount(1, $items);
            $this->assertTrue(HttpRequest::create((string) $items[0]['photo_url'])->hasValidSignature());
        }
    }

    public function test_booking_show_denies_a_third_party_and_never_mints_signed_urls(): void
    {
        // La confidentialité des photos privées repose sur deux invariants : la
        // signature est inforgeable, ET les URLs signées ne sont sérialisées que
        // pour les deux parties. Ce test verrouille le second : un tiers
        // authentifié (ni Face ni Producteur du booking) est refusé par la policy
        // `view` AVANT toute sérialisation — il ne reçoit jamais d'URL signée
        // valide qui court-circuiterait la garde signature de la route média.
        $booking = $this->createBookingWithPhoto();

        $stranger = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->actingAs($stranger)
            ->getJson('/api/v1/bookings/'.$booking->uuid)
            ->assertForbidden();
    }

    // =========================================================================
    // Validation (422 français, rien n'est persisté)
    // =========================================================================

    public function test_three_photos_are_rejected_and_nothing_is_persisted(): void
    {
        $data = $this->validUgcBookingData();
        $data['product_photos'] = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $data, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.product_photos.0', 'Vous ne pouvez joindre que 2 photos du produit.');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_pdf_is_rejected_with_french_message(): void
    {
        $data = $this->validUgcBookingData();
        $data['product_photos'] = [
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ];

        $response = $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $data, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_photos.0']);

        $this->assertContains(
            'Chaque photo du produit doit être une image.',
            $response->json('errors')['product_photos.0'],
        );
        $this->assertSame(0, Booking::count());
    }

    public function test_oversized_photo_is_rejected(): void
    {
        $data = $this->validUgcBookingData();
        $data['product_photos'] = [
            UploadedFile::fake()->image('big.jpg')->size(9 * 1024), // 9 Mo > 8 Mo
        ];

        $response = $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $data, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_photos.0']);

        $this->assertContains(
            'Chaque photo du produit ne doit pas dépasser 8 Mo.',
            $response->json('errors')['product_photos.0'],
        );
        $this->assertSame(0, Booking::count());
    }

    public function test_mission_rejects_three_photos_with_french_message(): void
    {
        $data = $this->validUgcMissionData();
        $data['product_photos'] = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $data, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.product_photos.0', 'Vous ne pouvez joindre que 2 photos du produit.');

        $this->assertSame(0, Mission::count());
        $this->assertSame(0, ProductPhoto::count());
    }

    // =========================================================================
    // Création mission UGC avec photo (disque public, URLs asset directes)
    // =========================================================================

    public function test_ugc_mission_with_photo_stores_public_row_and_queues_job(): void
    {
        Queue::fake();

        $data = $this->validUgcMissionData();
        $data['product_photos'] = [UploadedFile::fake()->image('produit.jpg', 900, 900)];

        $response = $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $data, ['Accept' => 'application/json']);

        $response->assertCreated();

        $mission = Mission::firstOrFail();
        $photo = $mission->productPhotos()->firstOrFail();

        $this->assertSame('public', $photo->disk);
        $this->assertSame(1, $photo->position);
        Storage::disk('public')->assertExists('products/'.$photo->filename);
        $this->assertSame([], Storage::disk('local')->allFiles());

        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_PRODUCT_PHOTO
                && $job->targetId === $photo->id
        );

        // URLs publiques directes (asset storage), pas de route signée.
        $item = $response->json('data.product_photos.0');
        $this->assertStringContainsString('/storage/products/'.$photo->filename, (string) $item['photo_url']);
        $this->assertStringNotContainsString('/product-photos/', (string) $item['grid_url']);
    }

    public function test_face_mission_detail_exposes_product_photos(): void
    {
        Queue::fake();

        $data = $this->validUgcMissionData();
        $data['type_compensation'] = 'hybrid'; // hybride = publiée directement (D-8.4.c)
        $data['nombre_videos'] = 2;
        $data['montant_remuneration'] = 15000;
        $data['product_photos'] = [UploadedFile::fake()->image('produit.jpg')];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $data, ['Accept' => 'application/json'])
            ->assertCreated();

        $mission = Mission::firstOrFail();

        // Face abonnée (gate FR5) : le détail expose les photos aux candidates.
        \App\Models\FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);

        $items = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions/'.$mission->uuid)
            ->assertOk()
            ->json('data.product_photos');

        $this->assertCount(1, $items);
        $this->assertStringContainsString('/storage/products/', (string) $items[0]['photo_url']);
    }

    // =========================================================================
    // Streaming signé (la signature EST la garde)
    // =========================================================================

    public function test_signed_original_url_returns_the_file(): void
    {
        $booking = $this->createBookingWithPhoto();
        $photo = $booking->productPhotos()->firstOrFail();

        $url = URL::temporarySignedRoute(
            'product-photos.original',
            now()->addMinutes(30),
            ['productPhoto' => $photo->uuid],
        );

        $this->get($url)->assertOk();
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $booking = $this->createBookingWithPhoto();
        $photo = $booking->productPhotos()->firstOrFail();

        $this->get('/api/v1/product-photos/'.$photo->uuid.'/original')
            ->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        $booking = $this->createBookingWithPhoto();
        $photo = $booking->productPhotos()->firstOrFail();

        $url = URL::temporarySignedRoute(
            'product-photos.original',
            now()->subMinute(), // déjà expirée
            ['productPhoto' => $photo->uuid],
        );

        $this->get($url)->assertForbidden();
    }

    public function test_missing_variant_returns_404_on_signed_route(): void
    {
        $booking = $this->createBookingWithPhoto(); // variantes pas encore générées
        $photo = $booking->productPhotos()->firstOrFail();

        $url = URL::temporarySignedRoute(
            'product-photos.grid',
            now()->addMinutes(30),
            ['productPhoto' => $photo->uuid],
        );

        $this->get($url)->assertNotFound();
    }

    // =========================================================================
    // Générateur : variantes grid+large sur le disque de la row
    // =========================================================================

    public function test_job_generates_grid_and_large_on_the_private_disk(): void
    {
        $booking = $this->createBookingWithPhoto();
        /** @var ProductPhoto $photo */
        $photo = $booking->productPhotos()->firstOrFail();

        (new GenerateImageVariants(GenerateImageVariants::TYPE_PRODUCT_PHOTO, $photo->id))
            ->handle(app(ImageVariantGenerator::class));

        $photo->refresh();
        $expectedWebp = pathinfo($photo->filename, PATHINFO_FILENAME).'.webp';
        $this->assertSame($expectedWebp, $photo->grid);
        $this->assertSame($expectedWebp, $photo->large);

        Storage::disk('local')->assertExists('products/grid/'.$photo->grid);
        Storage::disk('local')->assertExists('products/large/'.$photo->large);
        // grid + large SEULEMENT (pas de thumbnail 150 ni medium 800), et rien sur public.
        $this->assertSame([], Storage::disk('local')->files('products/thumbnails'));
        $this->assertSame([], Storage::disk('local')->files('products/medium'));
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Après génération, grid_url/large_url pointent les routes signées des variantes.
        $this->assertStringContainsString('/grid', (string) $photo->grid_url);
        $this->assertStringContainsString('/large', (string) $photo->large_url);
        $url = URL::temporarySignedRoute('product-photos.grid', now()->addMinutes(30), ['productPhoto' => $photo->uuid]);
        $this->get($url)->assertOk();
    }

    public function test_job_generates_variants_on_the_public_disk_for_mission_photos(): void
    {
        Queue::fake();

        $data = $this->validUgcMissionData();
        $data['product_photos'] = [UploadedFile::fake()->image('produit.jpg', 900, 900)];
        $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $data, ['Accept' => 'application/json'])
            ->assertCreated();

        /** @var ProductPhoto $photo */
        $photo = ProductPhoto::firstOrFail();

        (new GenerateImageVariants(GenerateImageVariants::TYPE_PRODUCT_PHOTO, $photo->id))
            ->handle(app(ImageVariantGenerator::class));

        $photo->refresh();
        Storage::disk('public')->assertExists('products/grid/'.$photo->grid);
        Storage::disk('public')->assertExists('products/large/'.$photo->large);
        $this->assertStringContainsString('/storage/products/grid/'.$photo->grid, (string) $photo->grid_url);
    }

    // =========================================================================
    // Cleanup : throw pendant attach + suppression mission
    // =========================================================================

    public function test_attach_cleans_stored_files_when_a_row_insert_fails(): void
    {
        $booking = $this->makeUgcBooking();
        // Row pré-existante en position 1 → l'insert d'attach() viole l'unique
        // (owner, kind, position) et doit nettoyer le fichier déjà écrit.
        $booking->productPhotos()->create([
            'kind' => 'product',
            'position' => 1,
            'disk' => 'local',
            'filename' => 'pre-existing.jpg',
        ]);

        try {
            app(ProductPhotoService::class)->attach(
                $booking,
                [UploadedFile::fake()->image('photo.jpg')],
                'local',
            );
            $this->fail('Expected a unique-constraint QueryException.');
        } catch (QueryException) {
            // attendu
        }

        // Le fichier écrit avant le throw a été nettoyé (le rollback DB ne
        // rollback pas le filesystem).
        $this->assertCount(0, array_filter(
            Storage::disk('local')->files('products'),
            fn (string $f) => ! str_contains($f, 'pre-existing'),
        ));
    }

    public function test_attach_throws_and_persists_nothing_when_the_original_write_fails(): void
    {
        // Les disques `local`/`public` sont `throw => false` : putFileAs RETOURNE
        // false sur échec d'écriture. attach() doit le détecter et lever, sinon
        // une row pointerait un fichier absent (vignette cassée permanente).
        $booking = $this->makeUgcBooking();

        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            app(ProductPhotoService::class)->attach(
                $booking,
                [UploadedFile::fake()->image('photo.jpg')],
                'local',
            );
            $this->fail('Expected a RuntimeException on a failed original write.');
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertSame(0, $booking->productPhotos()->count());
    }

    public function test_deleting_a_mission_removes_photo_files_and_rows(): void
    {
        Queue::fake();

        $data = $this->validUgcMissionData();
        $data['type_compensation'] = 'hybrid'; // publiée directement → supprimable
        $data['nombre_videos'] = 2;
        $data['montant_remuneration'] = 15000;
        $data['product_photos'] = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $data, ['Accept' => 'application/json'])
            ->assertCreated();

        $mission = Mission::firstOrFail();
        $this->assertSame(2, $mission->productPhotos()->count());
        $this->assertCount(2, Storage::disk('public')->files('products'));

        $this->actingAs($this->producerUser)
            ->deleteJson('/api/v1/producer/missions/'.$mission->uuid)
            ->assertOk();

        $this->assertSame(0, ProductPhoto::count());
        $this->assertSame([], Storage::disk('public')->files('products'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createBookingWithPhoto(): Booking
    {
        Queue::fake(); // pas d'encodage synchrone pendant la requête

        $data = $this->validUgcBookingData();
        $data['product_photos'] = [UploadedFile::fake()->image('produit.jpg', 900, 900)];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/bookings', $data, ['Accept' => 'application/json'])
            ->assertCreated();

        return Booking::firstOrFail();
    }

    private function makeUgcBooking(): Booking
    {
        return Booking::create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => \App\Enums\BookingStatus::Pending,
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
}
