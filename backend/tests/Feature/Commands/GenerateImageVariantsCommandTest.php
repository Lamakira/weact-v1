<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateImageVariantsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_retrofit_generates_only_the_missing_variants_for_all_entities(): void
    {
        // Legacy Face: original + thumbnail + medium already on disk,
        // grid/large missing (the pre-spec state of ~3000 production rows).
        $face = $this->makeLegacyFace();

        // Legacy Producer: original only.
        $producerFile = UploadedFile::fake()->image('producer.jpg', 1200, 1200);
        Storage::disk('public')->putFileAs('avatars/producers', $producerFile, 'producer.jpg');
        $producer = Producer::factory()->create([
            'profile_photo' => 'producer.jpg',
            'profile_photo_thumbnail' => null,
        ]);

        // Legacy album photo: original only.
        $albumFile = UploadedFile::fake()->image('album.jpg', 1200, 1200);
        Storage::disk('public')->putFileAs('avatars/faces/albums', $albumFile, 'album.jpg');
        $facePhoto = FacePhoto::factory()->create([
            'face_id' => $face->id,
            'filename' => 'album.jpg',
            'thumbnail' => null,
        ]);

        // Face without any photo: ignored by the whereNotNull scope.
        Face::factory()->create(['profile_photo' => null, 'profile_photo_thumbnail' => null]);

        $this->artisan('images:generate-variants')
            // Face: grid + large generated (thumb + medium skipped);
            // Producer + FacePhoto: all four generated → 2 + 4 + 4 = 10.
            ->expectsOutputToContain('10 variante(s) générée(s), 2 déjà présente(s) (skipped), 0 source(s) manquante(s), 0 ligne(s) en échec.')
            ->assertSuccessful();

        $face->refresh();
        $this->assertNotNull($face->profile_photo_grid);
        $this->assertNotNull($face->profile_photo_large);
        Storage::disk('public')->assertExists('avatars/faces/grid/'.$face->profile_photo_grid);
        Storage::disk('public')->assertExists('avatars/faces/large/'.$face->profile_photo_large);
        // Pre-existing variants untouched
        Storage::disk('public')->assertExists('avatars/faces/thumbnails/'.$face->profile_photo_thumbnail);
        Storage::disk('public')->assertExists('avatars/faces/medium/'.$face->profile_photo_medium);

        $producer->refresh();
        $this->assertNotNull($producer->profile_photo_thumbnail);
        $this->assertNotNull($producer->profile_photo_medium);
        $this->assertNotNull($producer->profile_photo_grid);
        $this->assertNotNull($producer->profile_photo_large);

        $facePhoto->refresh();
        $this->assertNotNull($facePhoto->thumbnail);
        $this->assertNotNull($facePhoto->medium);
        $this->assertNotNull($facePhoto->grid);
        $this->assertNotNull($facePhoto->large);
        Storage::disk('public')->assertExists('avatars/faces/albums/grid/'.$facePhoto->grid);
        Storage::disk('public')->assertExists('avatars/faces/albums/large/'.$facePhoto->large);
    }

    public function test_rerun_regenerates_nothing(): void
    {
        $this->makeLegacyFace();

        $this->artisan('images:generate-variants')->assertSuccessful();

        $this->artisan('images:generate-variants')
            ->expectsOutputToContain('0 variante(s) générée(s), 4 déjà présente(s) (skipped), 0 source(s) manquante(s), 0 ligne(s) en échec.')
            ->assertSuccessful();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $face = $this->makeLegacyFace();

        $this->artisan('images:generate-variants', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run terminé : 2 variante(s) générée(s), 2 déjà présente(s) (skipped), 0 source(s) manquante(s), 0 ligne(s) en échec.')
            ->assertSuccessful();

        $face->refresh();
        $this->assertNull($face->profile_photo_grid);
        $this->assertNull($face->profile_photo_large);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/large'));
    }

    public function test_missing_source_is_skipped_and_processing_continues(): void
    {
        // Row whose original file vanished from disk
        $orphan = Face::factory()->create([
            'profile_photo' => 'gone.jpg',
            'profile_photo_thumbnail' => null,
        ]);

        // Healthy row processed after the orphan (higher id, chunkById order)
        $healthy = $this->makeLegacyFace();

        $this->artisan('images:generate-variants')
            ->expectsOutputToContain('2 variante(s) générée(s), 2 déjà présente(s) (skipped), 1 source(s) manquante(s), 0 ligne(s) en échec.')
            ->assertSuccessful();

        $orphan->refresh();
        $this->assertNull($orphan->profile_photo_grid);

        $healthy->refresh();
        $this->assertNotNull($healthy->profile_photo_grid);
        $this->assertNotNull($healthy->profile_photo_large);
    }

    public function test_corrupt_source_is_counted_as_failed_and_processing_continues(): void
    {
        Storage::disk('public')->put('avatars/faces/corrupt.jpg', 'not an image');
        Face::factory()->create([
            'profile_photo' => 'corrupt.jpg',
            'profile_photo_thumbnail' => null,
        ]);

        $healthy = $this->makeLegacyFace();

        // Per-row failures never abort the run but surface as a FAILURE exit
        // code (same convention as faces:purge-expired-media).
        $this->artisan('images:generate-variants')
            ->expectsOutputToContain('2 variante(s) générée(s), 2 déjà présente(s) (skipped), 0 source(s) manquante(s), 1 ligne(s) en échec.')
            ->assertFailed();

        $healthy->refresh();
        $this->assertNotNull($healthy->profile_photo_grid);
    }

    /**
     * A Face in the pre-spec production state: original + 150 thumbnail +
     * 800 medium present (files and columns), grid/large missing.
     */
    private function makeLegacyFace(): Face
    {
        $original = UploadedFile::fake()->image('face.jpg', 1200, 1200);
        Storage::disk('public')->putFileAs('avatars/faces', $original, 'face.jpg');
        Storage::disk('public')->put('avatars/faces/thumbnails/face.jpg', 'legacy thumbnail bytes');
        Storage::disk('public')->put('avatars/faces/medium/face.webp', 'legacy medium bytes');

        return Face::factory()->create([
            'profile_photo' => 'face.jpg',
            'profile_photo_thumbnail' => 'face.jpg',
            'profile_photo_medium' => 'face.webp',
        ]);
    }
}
