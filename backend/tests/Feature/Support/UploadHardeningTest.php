<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\DeliverableKind;
use App\Models\Face;
use App\Models\Producer;
use App\Services\Admin\ArticleService;
use App\Services\AgencyLogoService;
use App\Services\ProducerProfilePhotoService;
use App\Services\ProfilePhotoService;
use App\Services\Ugc\UgcDeliverableService;
use App\Support\UploadedMedia;
use App\Support\VideoMetadataStripper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

/**
 * Durcissement uploads : extension stockée dérivée du contenu (allowlist),
 * EXIF supprimé avec orientation conservée, remux ffmpeg sans métadonnées.
 */
class UploadHardeningTest extends TestCase
{
    use BuildsMediaFixtures;
    use RefreshDatabase;

    private function upload(string $name, string $bytes, string $mime = 'image/jpeg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }

    public function test_helper_sanity_fixture_really_carries_gps_exif(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ex');
        file_put_contents($path, $this->jpegWithExif(40, 20, 6));
        $exif = exif_read_data($path);

        $this->assertSame(6, $exif['Orientation']);
        $this->assertSame('N', $exif['GPSLatitudeRef']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileNames(): array
    {
        return [
            'html' => ['x.html'],
            'double ext' => ['x.php.jpg'],
            'svg' => ['x.svg'],
            'hta' => ['x.hta'],
            'no ext' => ['x'],
        ];
    }

    #[DataProvider('hostileNames')]
    public function test_profile_photo_extension_comes_from_content_not_client_name(string $clientName): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();

        $result = app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload($clientName, $this->jpegWithExif(40, 20, 1)));

        $this->assertSame('jpg', pathinfo($result['photo'], PATHINFO_EXTENSION));
        Storage::disk('public')->assertExists('avatars/faces/'.$result['photo']);
    }

    public function test_producer_photo_agency_logo_and_article_use_safe_extension(): void
    {
        Storage::fake('public');
        Queue::fake();
        $producer = Producer::factory()->create();

        $photo = app(ProducerProfilePhotoService::class)->uploadProfilePhoto($producer, $this->upload('x.html', $this->jpegWithExif(40, 20, 1)));
        $this->assertSame('jpg', pathinfo($photo['photo'], PATHINFO_EXTENSION));

        $agency = Producer::factory()->agency()->create();
        $logo = app(AgencyLogoService::class)->uploadLogo($agency, $this->upload('x.svg', $this->jpegWithExif(40, 20, 1)));
        $this->assertSame('jpg', pathinfo($logo['logo'], PATHINFO_EXTENSION));

        $filename = (new \ReflectionMethod(ArticleService::class, 'uploadFeaturedImage'))
            ->invoke(app(ArticleService::class), $this->upload('x.html', $this->jpegWithExif(40, 20, 1)));
        $this->assertSame('jpg', pathinfo($filename, PATHINFO_EXTENSION));
    }

    public function test_png_keeps_png_extension_and_non_allowlisted_content_is_rejected(): void
    {
        $im = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        $this->assertSame('png', UploadedMedia::imageExtension($this->upload('x.html', $png, 'image/png')));

        $this->expectException(ValidationException::class);
        UploadedMedia::imageExtension($this->upload('x.jpg', '<html></html>', 'text/html'));
    }

    public function test_video_extension_is_allowlisted_by_mime(): void
    {
        $this->assertSame('mp4', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.hta', 10, 'video/mp4')));
        $this->assertSame('mov', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.html', 10, 'video/quicktime')));
        $this->assertSame('avi', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.mp4', 10, 'video/x-msvideo')));

        $this->expectException(ValidationException::class);
        UploadedMedia::videoExtension(UploadedFile::fake()->create('v.mp4', 10, 'text/html'));
    }

    public function test_stored_original_has_no_exif_and_orientation_is_applied_to_pixels(): void
    {
        Storage::fake('public');

        // 40x20 taggé orientation 6 (rotation 90 horaire) => 20x40 une fois orienté.
        $filename = UploadedMedia::storeImage('public', 'avatars/faces', $this->upload('p.jpg', $this->jpegWithExif(40, 20, 6)));

        $stored = Storage::disk('public')->path('avatars/faces/'.$filename);
        $exif = @exif_read_data($stored);

        $this->assertFalse(is_array($exif) && array_key_exists('GPSLatitude', $exif), 'GPS EXIF must be stripped');
        $this->assertFalse(is_array($exif) && array_key_exists('GPSLatitudeRef', $exif));
        $this->assertFalse(is_array($exif) && array_key_exists('Orientation', $exif));
        $this->assertStringNotContainsString('Exif', (string) file_get_contents($stored));

        [$width, $height] = getimagesize($stored);
        $this->assertSame(20, $width);
        $this->assertSame(40, $height);
    }

    public function test_image_dimension_rule_caps_each_side(): void
    {
        $this->assertSame(6000, UploadedMedia::MAX_IMAGE_DIMENSION);

        $validator = validator(
            ['photo' => UploadedFile::fake()->image('big.jpg', 6001, 10)],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );
        $this->assertTrue($validator->fails());

        $validator = validator(
            ['photo' => UploadedFile::fake()->image('ok.jpg', 600, 10)],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );
        $this->assertFalse($validator->fails());
    }

    public function test_video_metadata_stripper_remuxes_with_map_metadata_minus_one_and_copy(): void
    {
        Process::fake();
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        file_put_contents($path, 'original');
        // Le faux processus ne produit rien : on pré-crée la sortie attendue.
        file_put_contents($path.'.stripped.mp4', 'stripped');

        VideoMetadataStripper::strip($path);

        Process::assertRan(function ($process) use ($path): bool {
            $cmd = $process->command;
            $joined = implode(' ', $cmd);

            return str_contains($joined, '-map_metadata -1')
                && str_contains($joined, '-c copy')
                && in_array($path, $cmd, true)
                && ! str_contains($joined, 'libx264');
        });
        $this->assertSame('stripped', file_get_contents($path));
    }

    public function test_video_metadata_stripper_failure_keeps_original_and_throws(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        file_put_contents($path, 'original');

        try {
            VideoMetadataStripper::strip($path);
            $this->fail('Expected exception');
        } catch (\RuntimeException) {
            $this->assertSame('original', file_get_contents($path));
        }
    }

    public function test_deliverable_upload_survives_a_failing_remux_without_inconsistent_state(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($src)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::fake('local');
        Log::spy();
        // Seul le remux échoue ; ffprobe/miniature (php-ffmpeg) tournent pour de vrai.
        Process::fake(['*-map_metadata*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

        $upload = new UploadedFile($src, 'evil.hta', null, null, true);
        $media = app(UgcDeliverableService::class)->storeMedia($upload, DeliverableKind::Unboxing);

        $disk = Storage::disk('local');
        $this->assertStringEndsWith('.mp4', $media['video_path']); // extension depuis le contenu
        $disk->assertExists($media['video_path']);                  // original conservé
        $disk->assertExists($media['thumbnail_path']);              // miniature : tout est cohérent
        $this->assertSame([], glob(dirname($disk->path($media['video_path'])).'/*.stripped.*'));
        Log::shouldHaveReceived('warning')->once();
    }
}
