<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateImageVariants;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Models\User;
use App\Support\ImageVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Tests\TestCase;

class GenerateImageVariantsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    // =========================================================================
    // Upload requests dispatch the job and generate nothing synchronously
    // =========================================================================

    public function test_face_profile_upload_queues_job_and_generates_no_variant_synchronously(): void
    {
        Queue::fake();

        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/face/profile/photo', [
                'photo' => UploadedFile::fake()->image('profile.jpg', 500, 500),
            ])
            ->assertOk();

        $face->refresh();
        $this->assertNotNull($face->profile_photo);
        $this->assertNull($face->profile_photo_thumbnail);
        $this->assertNull($face->profile_photo_medium);
        $this->assertNull($face->profile_photo_grid);
        $this->assertNull($face->profile_photo_large);

        Storage::disk('public')->assertExists('avatars/faces/'.$face->profile_photo);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/thumbnails'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/medium'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/large'));

        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_FACE
                && $job->targetId === $face->id
        );
    }

    public function test_album_upload_queues_job_and_generates_no_variant_synchronously(): void
    {
        Queue::fake();

        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/face/album', [
                'photo' => UploadedFile::fake()->image('album.jpg', 500, 500),
            ])
            ->assertCreated();

        /** @var FacePhoto $photo */
        $photo = FacePhoto::firstOrFail();
        $this->assertNull($photo->thumbnail);
        $this->assertNull($photo->medium);
        $this->assertNull($photo->grid);
        $this->assertNull($photo->large);

        Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/albums/thumbnails'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/albums/medium'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/albums/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/albums/large'));

        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_FACE_PHOTO
                && $job->targetId === $photo->id
        );
    }

    public function test_producer_profile_upload_queues_job_and_generates_no_variant_synchronously(): void
    {
        Queue::fake();

        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/producer/profile/photo', [
                'photo' => UploadedFile::fake()->image('profile.jpg', 500, 500),
            ])
            ->assertOk();

        $producer->refresh();
        $this->assertNotNull($producer->profile_photo);
        $this->assertNull($producer->profile_photo_thumbnail);
        $this->assertNull($producer->profile_photo_medium);
        $this->assertNull($producer->profile_photo_grid);
        $this->assertNull($producer->profile_photo_large);

        $this->assertSame([], Storage::disk('public')->files('avatars/producers/thumbnails'));
        $this->assertSame([], Storage::disk('public')->files('avatars/producers/medium'));
        $this->assertSame([], Storage::disk('public')->files('avatars/producers/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/producers/large'));

        Queue::assertPushed(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job) => $job->targetType === GenerateImageVariants::TYPE_PRODUCER
                && $job->targetId === $producer->id
        );
    }

    // =========================================================================
    // The job generates the four variants and fills the columns
    // =========================================================================

    public function test_job_generates_all_four_variants_for_a_face(): void
    {
        $face = $this->makeFaceWithOriginal();

        $this->runJob(GenerateImageVariants::forModel($face));

        $face->refresh();
        $this->assertSame($face->profile_photo, $face->profile_photo_thumbnail);
        $expectedWebp = pathinfo((string) $face->profile_photo, PATHINFO_FILENAME).'.webp';
        $this->assertSame($expectedWebp, $face->profile_photo_medium);
        $this->assertSame($expectedWebp, $face->profile_photo_grid);
        $this->assertSame($expectedWebp, $face->profile_photo_large);

        Storage::disk('public')->assertExists('avatars/faces/thumbnails/'.$face->profile_photo_thumbnail);
        Storage::disk('public')->assertExists('avatars/faces/medium/'.$face->profile_photo_medium);
        Storage::disk('public')->assertExists('avatars/faces/grid/'.$face->profile_photo_grid);
        Storage::disk('public')->assertExists('avatars/faces/large/'.$face->profile_photo_large);

        // Variant widths honour the target sizes (original is 1800px wide)
        $this->assertSame(400, Image::read(Storage::disk('public')->get('avatars/faces/grid/'.$face->profile_photo_grid))->width());
        $this->assertSame(1600, Image::read(Storage::disk('public')->get('avatars/faces/large/'.$face->profile_photo_large))->width());
        $this->assertSame(800, Image::read(Storage::disk('public')->get('avatars/faces/medium/'.$face->profile_photo_medium))->width());
    }

    public function test_job_generates_all_four_variants_for_a_face_photo(): void
    {
        $photo = $this->makeFacePhotoWithOriginal();

        $this->runJob(GenerateImageVariants::forModel($photo));

        $photo->refresh();
        $expectedWebp = pathinfo((string) $photo->filename, PATHINFO_FILENAME).'.webp';
        $this->assertSame($photo->filename, $photo->thumbnail);
        $this->assertSame($expectedWebp, $photo->medium);
        $this->assertSame($expectedWebp, $photo->grid);
        $this->assertSame($expectedWebp, $photo->large);

        Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/'.$photo->thumbnail);
        Storage::disk('public')->assertExists('avatars/faces/albums/medium/'.$photo->medium);
        Storage::disk('public')->assertExists('avatars/faces/albums/grid/'.$photo->grid);
        Storage::disk('public')->assertExists('avatars/faces/albums/large/'.$photo->large);
    }

    public function test_job_generates_all_four_variants_for_a_producer(): void
    {
        $producer = $this->makeProducerWithOriginal();

        $this->runJob(GenerateImageVariants::forModel($producer));

        $producer->refresh();
        $expectedWebp = pathinfo((string) $producer->profile_photo, PATHINFO_FILENAME).'.webp';
        $this->assertSame($producer->profile_photo, $producer->profile_photo_thumbnail);
        $this->assertSame($expectedWebp, $producer->profile_photo_medium);
        $this->assertSame($expectedWebp, $producer->profile_photo_grid);
        $this->assertSame($expectedWebp, $producer->profile_photo_large);

        Storage::disk('public')->assertExists('avatars/producers/thumbnails/'.$producer->profile_photo_thumbnail);
        Storage::disk('public')->assertExists('avatars/producers/medium/'.$producer->profile_photo_medium);
        Storage::disk('public')->assertExists('avatars/producers/grid/'.$producer->profile_photo_grid);
        Storage::disk('public')->assertExists('avatars/producers/large/'.$producer->profile_photo_large);
    }

    public function test_variants_are_never_upscaled(): void
    {
        $face = $this->makeFaceWithOriginal(width: 200, height: 200);

        $this->runJob(GenerateImageVariants::forModel($face));

        $face->refresh();
        // scaleDown: a 200px original yields grid/medium/large variants capped
        // at the original width, never stretched to 400/800/1600.
        $this->assertSame(200, Image::read(Storage::disk('public')->get('avatars/faces/grid/'.$face->profile_photo_grid))->width());
        $this->assertSame(200, Image::read(Storage::disk('public')->get('avatars/faces/medium/'.$face->profile_photo_medium))->width());
        $this->assertSame(200, Image::read(Storage::disk('public')->get('avatars/faces/large/'.$face->profile_photo_large))->width());
    }

    public function test_job_only_generates_missing_variants(): void
    {
        $face = $this->makeFaceWithOriginal();

        // First run generates everything, second run must generate nothing
        $this->runJob(GenerateImageVariants::forModel($face));
        $face->refresh();

        $result = app(ImageVariantGenerator::class)->generate($face);

        $this->assertSame([], $result['generated']);
        $this->assertSame(['thumbnail', 'medium', 'grid', 'large'], $result['skipped']);
        $this->assertFalse($result['missing_source']);
    }

    // =========================================================================
    // Stale-write guard (row changed while encoding)
    // =========================================================================

    public function test_variants_are_discarded_when_the_row_was_deleted_during_generation(): void
    {
        $face = $this->makeFaceWithOriginal();

        // Simulate the race: the row disappears while this run is encoding —
        // the stale in-memory instance still points to the original file.
        Face::query()->whereKey($face->id)->delete();

        $result = app(ImageVariantGenerator::class)->generate($face);

        // The guarded update matched no row: the files written by this run
        // must be cleaned up, not left orphaned on disk.
        $this->assertSame([], $result['generated']);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/thumbnails'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/medium'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/large'));
    }

    public function test_variants_are_discarded_when_the_original_changed_during_generation(): void
    {
        $face = $this->makeFaceWithOriginal();

        // Simulate a re-upload race: another request rewrote the row to a new
        // original while this run was encoding the old one.
        Face::query()->whereKey($face->id)->update(['profile_photo' => 'newer.jpg']);

        $result = app(ImageVariantGenerator::class)->generate($face);

        $this->assertSame([], $result['generated']);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/grid'));
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/large'));

        // The newer row state was never overwritten by the stale run
        $face->refresh();
        $this->assertSame('newer.jpg', $face->profile_photo);
        $this->assertNull($face->profile_photo_grid);
        $this->assertNull($face->profile_photo_large);
    }

    // =========================================================================
    // No-throw guarantees (anti queue-poison)
    // =========================================================================

    public function test_job_is_a_noop_when_the_model_was_deleted(): void
    {
        $job = new GenerateImageVariants(GenerateImageVariants::TYPE_FACE_PHOTO, 999999);

        $this->runJob($job);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_job_is_a_noop_on_unknown_target_type(): void
    {
        $job = new GenerateImageVariants('unknown_type', 1);

        $this->runJob($job);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_job_is_a_noop_when_the_source_file_is_missing(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => 'missing.jpg',
            'profile_photo_thumbnail' => null,
        ]);

        $this->runJob(GenerateImageVariants::forModel($face));

        $face->refresh();
        $this->assertNull($face->profile_photo_thumbnail);
        $this->assertNull($face->profile_photo_grid);
        $this->assertNull($face->profile_photo_large);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_job_does_not_throw_on_a_corrupt_source_file(): void
    {
        $face = Face::factory()->create([
            'profile_photo' => 'corrupt.jpg',
            'profile_photo_thumbnail' => null,
        ]);
        Storage::disk('public')->put('avatars/faces/corrupt.jpg', 'this is not an image');

        $this->runJob(GenerateImageVariants::forModel($face));

        $face->refresh();
        $this->assertNull($face->profile_photo_grid);
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/grid'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function runJob(GenerateImageVariants $job): void
    {
        $job->handle(app(ImageVariantGenerator::class));
    }

    private function makeFaceWithOriginal(int $width = 1800, int $height = 1800): Face
    {
        $file = UploadedFile::fake()->image('photo.jpg', $width, $height);
        $filename = 'original.jpg';
        Storage::disk('public')->putFileAs('avatars/faces', $file, $filename);

        // FaceFactory seeds a random profile_photo_thumbnail — null it so the
        // job derives every variant filename from the original.
        return Face::factory()->create([
            'profile_photo' => $filename,
            'profile_photo_thumbnail' => null,
        ]);
    }

    private function makeProducerWithOriginal(): Producer
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1800, 1800);
        $filename = 'original.jpg';
        Storage::disk('public')->putFileAs('avatars/producers', $file, $filename);

        return Producer::factory()->create(['profile_photo' => $filename]);
    }

    private function makeFacePhotoWithOriginal(): FacePhoto
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1800, 1800);
        $filename = 'original.jpg';
        Storage::disk('public')->putFileAs('avatars/faces/albums', $file, $filename);

        return FacePhoto::factory()->create([
            'filename' => $filename,
            'thumbnail' => null,
        ]);
    }
}
