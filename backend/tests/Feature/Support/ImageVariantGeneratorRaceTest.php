<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\Face;
use App\Support\ImageVariantGenerator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Mockery;
use Tests\TestCase;

/**
 * Infra-failure / concurrency paths of ImageVariantGenerator that require a
 * mocked disk: the real (faked) 'public' disk never returns false on put() nor
 * null on get() for a file the top-of-method exists() just confirmed.
 */
class ImageVariantGeneratorRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_raises_and_claims_no_column_when_a_variant_write_fails(): void
    {
        $face = $this->fakeFace();

        // 'public' disk is 'throw' => false, so a failed write returns false.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturnTrue();
        $disk->shouldReceive('get')->andReturn($this->validJpegBytes());
        $disk->shouldReceive('put')->andReturnFalse();
        $disk->shouldReceive('delete')->andReturnTrue();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            app(ImageVariantGenerator::class)->generate($face);
            $this->fail('Expected a RuntimeException on a failed variant write.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Failed to write image variant', $e->getMessage());
        }

        // The column must NOT be claimed for a file that was never written —
        // otherwise the accessor would serve a 404 with the original-fallback
        // defeated, and the retrofit would report a false-clean run.
        $face->refresh();
        $this->assertNull($face->profile_photo_thumbnail);
        $this->assertNull($face->profile_photo_medium);
        $this->assertNull($face->profile_photo_grid);
        $this->assertNull($face->profile_photo_large);
    }

    public function test_generate_reports_missing_source_when_the_original_vanishes_before_decode(): void
    {
        $face = $this->fakeFace();

        // exists() passes at the top of generate(), then the original is gone
        // by the time get() runs (concurrent delete / re-upload) → get()
        // returns null on the 'throw' => false disk.
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturnTrue();
        $disk->shouldReceive('get')->andReturnNull();
        $disk->shouldReceive('delete')->andReturnTrue();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $result = app(ImageVariantGenerator::class)->generate($face);

        // Treated as a benign source-changed race (a skip), not a corrupt-source
        // failure that would flip the retrofit command to a FAILURE exit.
        $this->assertTrue($result['missing_source']);
        $this->assertSame([], $result['generated']);
    }

    private function fakeFace(): Face
    {
        return Face::factory()->create([
            'profile_photo' => 'original.jpg',
            'profile_photo_thumbnail' => null,
            'profile_photo_medium' => null,
            'profile_photo_grid' => null,
            'profile_photo_large' => null,
        ]);
    }

    private function validJpegBytes(): string
    {
        return (string) Image::create(300, 300)->toJpeg();
    }
}
