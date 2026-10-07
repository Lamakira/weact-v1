<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\DeliverableKind;
use App\Enums\FaceVideoType;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Services\Admin\ArticleService;
use App\Services\AgencyLogoService;
use App\Services\FaceVideoService;
use App\Services\PresentationVideoService;
use App\Services\ProducerProfilePhotoService;
use App\Services\ProfilePhotoService;
use App\Services\Ugc\UgcDeliverableService;
use App\Support\UploadedMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function test_png_keeps_png_extension_and_non_image_content_is_rejected_under_the_field_name(): void
    {
        Storage::fake('public');
        $im = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        $name = UploadedMedia::storeImage('public', 'avatars/faces', $this->upload('x.html', $png, 'image/png'), 'photo');
        $this->assertSame('png', pathinfo($name, PATHINFO_EXTENSION));

        try {
            UploadedMedia::storeImage('public', 'avatars/faces', $this->upload('x.jpg', '<html></html>', 'image/jpeg'), 'photo');
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertSame(['photo'], array_keys($e->errors()), 'clé = nom réel du champ, pas "file"');
        }
    }

    public function test_video_extension_is_allowlisted_by_mime_and_error_is_keyed_by_field(): void
    {
        $this->assertSame('mp4', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.hta', 10, 'video/mp4'), 'video'));
        $this->assertSame('mov', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.html', 10, 'video/quicktime'), 'video'));
        $this->assertSame('avi', UploadedMedia::videoExtension(UploadedFile::fake()->create('v.mp4', 10, 'video/x-msvideo'), 'video'));

        try {
            UploadedMedia::videoExtension(UploadedFile::fake()->create('v.mp4', 10, 'text/html'), 'video');
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertSame(['video'], array_keys($e->errors()));
        }
    }

    public function test_stored_original_drops_gps_but_keeps_orientation_and_pixels_untouched(): void
    {
        Storage::fake('public');

        $original = $this->jpegWithExif(40, 20, 6);
        $filename = UploadedMedia::storeImage('public', 'avatars/faces', $this->upload('p.jpg', $original), 'photo');

        $stored = Storage::disk('public')->path('avatars/faces/'.$filename);
        $exif = exif_read_data($stored);

        $this->assertSame(6, $exif['Orientation']);
        foreach (['GPSLatitude', 'GPSLatitudeRef', 'GPSInfo', 'Make'] as $key) {
            $this->assertArrayNotHasKey($key, $exif);
        }
        $this->assertStringNotContainsString('Canon', (string) file_get_contents($stored));

        // Aucun ré-encodage : mêmes dimensions (la rotation reste portée par l'Orientation).
        $this->assertSame([40, 20], array_slice(getimagesize($stored), 0, 2));
        $sos = fn (string $b): string => substr($b, (int) strpos($b, "\xFF\xDA"));
        $this->assertSame($sos($original), $sos((string) file_get_contents($stored)));
    }

    public function test_image_dimension_rule_caps_each_side_with_a_french_message(): void
    {
        $this->assertSame(8000, UploadedMedia::MAX_IMAGE_DIMENSION);

        $validator = validator(
            ['photo' => UploadedFile::fake()->image('big.jpg', 8001, 10)],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );
        $this->assertTrue($validator->fails());
        $this->assertSame('Image trop grande : 8000 pixels maximum par côté.', $validator->errors()->first('photo'));

        $validator = validator(
            ['photo' => UploadedFile::fake()->image('ok.jpg', 8000, 10)],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );
        $this->assertFalse($validator->fails());
    }

    public function test_store_media_derives_extension_from_content_and_does_not_remux_under_the_lock(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($src)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::fake('local');
        Process::fake();

        $upload = new UploadedFile($src, 'evil.hta', null, null, true);
        $media = app(UgcDeliverableService::class)->storeMedia($upload, DeliverableKind::Unboxing);

        $disk = Storage::disk('local');
        $this->assertStringEndsWith('.mp4', $media['video_path']);
        $disk->assertExists($media['video_path']);
        $disk->assertExists($media['thumbnail_path']);
        // Le remux est fait par upload() APRÈS le commit, jamais dans storeMedia (appelé sous lock).
        Process::assertNothingRan();
    }

    public function test_portfolio_and_presentation_videos_are_remuxed_after_commit_and_survive_a_remux_failure(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($src)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::fake('public');
        $face = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $baseLevel = DB::transactionLevel();
        $levels = [];
        Process::fake(function () use (&$levels) {
            $levels[] = DB::transactionLevel();

            return Process::result(errorOutput: 'boom', exitCode: 1);
        });

        $portfolio = app(FaceVideoService::class)->uploadVideo($face, FaceVideoType::Acting, new UploadedFile($src, 'evil.hta', null, null, true));
        $presentation = app(PresentationVideoService::class)->uploadPresentationVideo($face, new UploadedFile($src, 'evil.html', null, null, true));

        $this->assertSame([$baseLevel, $baseLevel], $levels, 'remux hors transaction (et hors lock de quota)');
        $this->assertSame('mp4', pathinfo($portfolio->filename, PATHINFO_EXTENSION));
        Storage::disk('public')->assertExists('videos/faces/acting/'.$portfolio->filename);
        Storage::disk('public')->assertExists('videos/faces/presentation/'.$presentation['video']);
        $this->assertSame(1, $face->videos()->count());
        $this->assertSame($presentation['video'], $face->fresh()->presentation_video);
    }

    public function test_profile_photo_endpoint_rejects_oversized_dimensions_with_french_message(): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();
        $user = \App\Models\User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        $this->actingAs($user)
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->image('big.jpg', 8001, 10)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo'])
            ->assertJsonFragment(['Image trop grande : 8000 pixels maximum par côté.']);
    }
}
