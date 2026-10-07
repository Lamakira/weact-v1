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

    private function pngHeaderOnly(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    public function test_image_dimension_rule_accepts_50mp_phone_photos_and_caps_at_12000_with_a_french_message(): void
    {
        $this->assertSame(12000, UploadedMedia::MAX_IMAGE_DIMENSION);

        $check = function (int $w, int $h): \Illuminate\Validation\Validator {
            return validator(
                ['photo' => $this->upload('p.png', $this->pngHeaderOnly($w, $h), 'image/png')],
                ['photo' => [UploadedMedia::maxDimensions()]],
            );
        };

        $this->assertFalse($check(8160, 6144)->fails(), '50 MP');
        $this->assertFalse($check(8064, 6048)->fails(), '48 MP');
        $this->assertFalse($check(12000, 100)->fails());

        $tooBig = $check(12001, 10);
        $this->assertTrue($tooBig->fails());
        $this->assertSame('Image trop grande : 12000 pixels maximum par côté.', $tooBig->errors()->first('photo'));
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

        // 2 vidéos x (remux + repli audio après l'échec) = 4 appels, tous hors transaction (et hors lock de quota)
        $this->assertCount(4, $levels);
        $this->assertSame([$baseLevel], array_values(array_unique($levels)));
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
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->image('big.jpg', 12001, 10)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo'])
            ->assertJsonFragment(['Image trop grande : 12000 pixels maximum par côté.']);
    }

    private function sofSeg(int $w, int $h): string
    {
        return $this->segment(0xC0, pack('CnnC', 8, $h, $w, 1)."\x01\x11\x00");
    }

    private function craftedA(): string
    {
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";

        return "\xFF\xD8\xFF\xE1\x00\x00".$this->segment(0xDB, "\x00".str_repeat("\x01", 64)).$this->sofSeg(30000, 30000)
            .$this->segment(0xC4, "\x00".str_repeat("\x00", 16)).$sos.str_repeat("\0", 64)."\xFF\xD9";
    }

    private function craftedB(): string
    {
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        $inner = $this->segment(0xDB, "\x00".str_repeat("\x01", 64)).$this->sofSeg(30000, 30000)
            .$this->segment(0xC4, "\x00".str_repeat("\x00", 16)).$sos.str_repeat("\0", 64)."\xFF\xD9";

        return "\xFF\xD8\xFF\x01".pack('n', 2 + strlen($inner)).$inner.$this->sofSeg(100, 100);
    }

    private function faceUser(): \App\Models\User
    {
        $face = Face::factory()->create();

        return \App\Models\User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
    }

    public function test_crafted_jpeg_with_zero_length_segment_is_rejected_at_the_endpoint(): void
    {
        Storage::fake('public');
        Queue::fake();
        $file = UploadedFile::fake()->createWithContent('a.jpg', $this->craftedA());

        $this->actingAs($this->faceUser())
            ->postJson('/api/v1/face/profile/photo', ['photo' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    /**
     * Rejeté ICI par la règle de validation (jpegDimensions traite FF01 comme un marqueur sans
     * paramètre et voit donc 30000x30000), pas par la garde de storeImage : celle-ci est prouvée
     * directement par test_store_image_checks_the_stripped_output_and_reports_the_pixel_cap_message.
     */
    public function test_crafted_jpeg_with_decoy_sof_behind_a_fake_length_is_rejected_at_the_endpoint_by_the_rule(): void
    {
        Storage::fake('public');
        Queue::fake();
        $file = UploadedFile::fake()->createWithContent('b.jpg', $this->craftedB());

        // getimagesize voit 100x100 mais notre lecteur d'en-tête voit 30000x30000 : la règle refuse.
        $this->actingAs($this->faceUser())
            ->postJson('/api/v1/face/profile/photo', ['photo' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $this->assertSame([], Storage::disk('public')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_store_image_checks_the_stripped_output_and_reports_the_pixel_cap_message(): void
    {
        Storage::fake('public');

        try {
            UploadedMedia::storeImage('public', 'avatars/faces', $this->upload('b.jpg', $this->craftedB()), 'photo');
            $this->fail('ValidationException attendue');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Image trop grande', $e->errors()['photo'][0]);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_pixel_cap_accepts_50mp_phones_and_rejects_54mp_with_the_french_message(): void
    {
        $this->assertSame(52_000_000, UploadedMedia::MAX_IMAGE_PIXELS);
        $check = fn (int $w, int $h) => validator(
            ['photo' => $this->upload('p.png', $this->pngHeaderOnly($w, $h), 'image/png')],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );

        $this->assertFalse($check(8160, 6144)->fails(), '50,1 MP');
        $this->assertFalse($check(8064, 6048)->fails(), '48,8 MP');

        $tooMany = $check(9000, 6000); // 54 MP, chaque côté < 12000
        $this->assertTrue($tooMany->fails());
        $this->assertSame('Image trop grande : 50 mégapixels maximum. Réduisez la résolution de la photo et réessayez.', $tooMany->errors()->first('photo'));
    }

    public function test_rule_fails_closed_when_only_getimagesize_cannot_read_a_jpeg(): void
    {
        // « FF 00 » avant le premier marqueur : notre lecteur le saute et lit 100x100, getimagesize() échoue.
        $sof = $this->sofSeg(100, 100);
        $dqt = $this->segment(0xDB, "\x00".str_repeat("\x01", 64));
        $jpeg = "\xFF\xD8\xFF\x00".$dqt.$sof;
        $path = tempnam(sys_get_temp_dir(), 'gis');
        file_put_contents($path, $jpeg);
        $this->assertSame([100, 100], \App\Support\ImageMetadataStripper::dimensions($path));
        $this->assertFalse(@getimagesize($path));

        $validator = validator(
            ['photo' => $this->upload('a.jpg', $jpeg)],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_variant_job_skips_an_oversized_stored_file_without_decoding_nor_throwing(): void
    {
        Storage::fake('public');
        \Illuminate\Support\Facades\Log::spy();
        $face = Face::factory()->create(['profile_photo' => 'huge.png']);
        Storage::disk('public')->put('avatars/faces/huge.png', $this->pngHeaderOnly(30000, 30000));

        // Le générateur refuse AVANT de décoder (rien n'est écrit) ...
        try {
            app(\App\Support\ImageVariantGenerator::class)->generate($face);
            $this->fail('RuntimeException attendue');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('non décodée', $e->getMessage());
        }
        $this->assertSame([], Storage::disk('public')->files('avatars/faces/thumbnails'));

        // ... et le job l'absorbe : warning, pas d'exception, donc pas de retry.
        \App\Jobs\GenerateImageVariants::forModel($face)->handle(app(\App\Support\ImageVariantGenerator::class));

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'échec de génération')
                && str_contains((string) ($context['exception_message'] ?? ''), 'non décodée'))
            ->once();
    }

    public function test_variant_job_skips_an_unreadable_stored_file_without_decoding_nor_throwing(): void
    {
        Storage::fake('public');
        \Illuminate\Support\Facades\Log::spy();
        $face = Face::factory()->create(['profile_photo' => 'broken.jpg']);
        Storage::disk('public')->put('avatars/faces/broken.jpg', $this->craftedA());

        \App\Jobs\GenerateImageVariants::forModel($face)->handle(app(\App\Support\ImageVariantGenerator::class));

        $this->assertSame([], Storage::disk('public')->files('avatars/faces/thumbnails'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains((string) ($context['exception_message'] ?? ''), 'non décodée'))
            ->once();
    }

    public function test_logo_thumbnail_job_skips_an_oversized_stored_logo_without_decoding(): void
    {
        Storage::fake('public');
        \Illuminate\Support\Facades\Log::spy();
        $agency = Producer::factory()->agency()->create(['agency_logo' => 'huge.png']);
        Storage::disk('public')->put('logos/agencies/huge.png', $this->pngHeaderOnly(30000, 30000));

        (new \App\Jobs\GenerateAgencyLogoThumbnail($agency->id, 'huge.png'))->handle();

        $this->assertNull($agency->fresh()->agency_logo_thumbnail);
        $this->assertSame([], Storage::disk('public')->files('logos/agencies/thumbnails'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'non décodée'))
            ->once();
    }

    private function truncatedBeforeSos(): string
    {
        return "\xFF\xD8".$this->segment(0xDB, "\x00".str_repeat("\x01", 64)).$this->sofSeg(100, 100);
    }

    private function scansJpeg(int $count): string
    {
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\x00";

        return "\xFF\xD8".$this->segment(0xDB, "\x00".str_repeat("\x01", 64)).$this->segment(0xC2, pack('CnnC', 8, 8, 8, 1)."\x01\x11\x00")
            .$this->segment(0xC4, "\x00".str_repeat("\x00", 16)).str_repeat($sos, $count)."\xFF\xD9";
    }

    public function test_jpeg_with_more_than_64_scans_is_rejected_at_the_endpoint_with_a_french_message(): void
    {
        Storage::fake('public');
        Queue::fake();

        $this->actingAs($this->faceUser())
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('bomb.jpg', $this->scansJpeg(65))])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo'])
            ->assertJsonFragment(['Image non supportée : trop de passes de compression JPEG.']);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_normal_progressive_jpeg_is_accepted_at_the_endpoint(): void
    {
        Storage::fake('public');
        Queue::fake();
        $im = imagecreatetruecolor(64, 48);
        imageinterlace($im, true);
        ob_start();
        imagejpeg($im, null, 85);
        $jpeg = (string) ob_get_clean();

        $this->actingAs($this->faceUser())
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('p.jpg', $jpeg)])
            ->assertOk();
    }

    public function test_png_with_a_huge_declared_chunk_length_gets_a_422_not_a_fatal(): void
    {
        Storage::fake('public');
        Queue::fake();
        $png = "\x89PNG\r\n\x1a\n".pack('N', 0x7FFFFFF0).'IHDR'.pack('NN', 100, 100).str_repeat("\0", 16);

        $this->actingAs($this->faceUser())
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('p.png', $png)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_zero_dimension_images_are_rejected_by_the_rule(): void
    {
        $validator = validator(
            ['photo' => $this->upload('z.png', $this->pngHeaderOnly(0, 10), 'image/png')],
            ['photo' => [UploadedMedia::maxDimensions()]],
        );

        $this->assertTrue($validator->fails());
    }

    private function seedVariants(Face $face): void
    {
        $name = (string) $face->profile_photo;
        Storage::disk('public')->put('avatars/faces/thumbnails/'.$name, 'thumb');
        Storage::disk('public')->put('avatars/faces/medium/'.pathinfo($name, PATHINFO_FILENAME).'.webp', 'medium');
        $face->update([
            'profile_photo_thumbnail' => $name,
            'profile_photo_medium' => pathinfo($name, PATHINFO_FILENAME).'.webp',
        ]);
    }

    public function test_failed_face_photo_replacement_keeps_the_previous_photo_and_variants(): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();
        $user = \App\Models\User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $face->refresh();
        $this->seedVariants($face);
        $old = (string) $face->profile_photo;

        // Tronqué avant le SOS : dimensions lisibles (la règle passe), le stripper refuse.
        $this->actingAs($user)
            ->postJson('/api/v1/face/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('t.jpg', $this->truncatedBeforeSos())])
            ->assertStatus(422);

        Storage::disk('public')->assertExists('avatars/faces/'.$old);
        Storage::disk('public')->assertExists('avatars/faces/thumbnails/'.$old);
        Storage::disk('public')->assertExists('avatars/faces/medium/'.pathinfo($old, PATHINFO_FILENAME).'.webp');
        $fresh = $face->fresh();
        $this->assertSame($old, $fresh->profile_photo);
        $this->assertSame($old, $fresh->profile_photo_thumbnail);
        $this->assertCount(3, Storage::disk('public')->allFiles());
    }

    public function test_successful_face_photo_replacement_deletes_the_previous_files_after_storing_the_new_one(): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();
        app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $face->refresh();
        $this->seedVariants($face);
        $old = (string) $face->profile_photo;

        $result = app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('b.jpg', $this->plainJpeg(30, 30)));

        $this->assertNotSame($old, $result['photo']);
        Storage::disk('public')->assertMissing('avatars/faces/'.$old);
        Storage::disk('public')->assertMissing('avatars/faces/thumbnails/'.$old);
        Storage::disk('public')->assertExists('avatars/faces/'.$result['photo']);
        $this->assertSame($result['photo'], $face->fresh()->profile_photo);
        $this->assertNull($face->fresh()->profile_photo_thumbnail);
    }

    public function test_failed_producer_photo_replacement_keeps_the_previous_photo(): void
    {
        Storage::fake('public');
        Queue::fake();
        $producer = Producer::factory()->create();
        $user = \App\Models\User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        app(ProducerProfilePhotoService::class)->uploadProfilePhoto($producer, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $producer->refresh();
        $old = (string) $producer->profile_photo;

        $this->actingAs($user)
            ->postJson('/api/v1/producer/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('t.jpg', $this->truncatedBeforeSos())])
            ->assertStatus(422);

        Storage::disk('public')->assertExists('avatars/producers/'.$old);
        $this->assertSame($old, $producer->fresh()->profile_photo);
    }

    public function test_failed_agency_logo_replacement_keeps_the_previous_logo_and_thumbnail(): void
    {
        Storage::fake('public');
        Queue::fake();
        $agency = Producer::factory()->agency()->create();
        $user = \App\Models\User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $agency->id]);
        app(AgencyLogoService::class)->uploadLogo($agency, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $agency->refresh();
        $old = (string) $agency->agency_logo;
        $thumb = pathinfo($old, PATHINFO_FILENAME).'.jpg';
        Storage::disk('public')->put('logos/agencies/thumbnails/'.$thumb, 'thumb');
        $agency->update(['agency_logo_thumbnail' => $thumb]);

        $this->actingAs($user)
            ->postJson('/api/v1/producer/profile/logo', ['logo' => UploadedFile::fake()->createWithContent('t.jpg', $this->truncatedBeforeSos())])
            ->assertStatus(422);

        Storage::disk('public')->assertExists('logos/agencies/'.$old);
        Storage::disk('public')->assertExists('logos/agencies/thumbnails/'.$thumb);
        $fresh = $agency->fresh();
        $this->assertSame($old, $fresh->agency_logo);
        $this->assertSame($thumb, $fresh->agency_logo_thumbnail);
    }

    public function test_failed_article_image_replacement_keeps_the_previous_image(): void
    {
        Storage::fake('public');
        $article = \App\Models\Article::factory()->create(['featured_image' => 'old.jpg']);
        Storage::disk('public')->put('articles/featured/old.jpg', 'old-bytes');

        try {
            app(ArticleService::class)->updateArticle($article, [], UploadedFile::fake()->createWithContent('t.jpg', $this->truncatedBeforeSos()));
            $this->fail('ValidationException attendue');
        } catch (ValidationException) {
            Storage::disk('public')->assertExists('articles/featured/old.jpg');
            $this->assertSame('old.jpg', $article->fresh()->featured_image);
        }
    }

    public function test_presentation_video_replacement_with_a_rejected_file_keeps_the_previous_video(): void
    {
        Storage::fake('public');
        $face = Face::factory()->create(['presentation_video' => 'old.mp4', 'presentation_video_thumbnail' => 'old.jpg']);
        Storage::disk('public')->put('videos/faces/presentation/old.mp4', 'old');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/old.jpg', 'thumb');

        try {
            app(PresentationVideoService::class)->uploadPresentationVideo($face, UploadedFile::fake()->create('v.mp4', 10, 'text/html'));
            $this->fail('ValidationException attendue');
        } catch (ValidationException) {
            Storage::disk('public')->assertExists('videos/faces/presentation/old.mp4');
            Storage::disk('public')->assertExists('videos/faces/presentation/thumbnails/old.jpg');
            $this->assertSame('old.mp4', $face->fresh()->presentation_video);
        }
    }

    /**
     * PNG asymétrique : fond blanc, bloc rouge 10x10 dans le coin haut-gauche, eXIf d'orientation donnée.
     */
    private function markedPng(int $width, int $height, int $orientation): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 0, 0, 9, 9, (int) imagecolorallocate($im, 255, 0, 0));
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        return substr($png, 0, 33).$this->pngChunk('eXIf', $this->exifTiff($orientation)).substr($png, 33);
    }

    private function isRedAt(string $path, int $x, int $y): bool
    {
        $im = imagecreatefromstring((string) file_get_contents($path));
        $rgb = imagecolorat($im, $x, $y);

        return (($rgb >> 16) & 0xFF) > 200 && (($rgb >> 8) & 0xFF) < 90 && ($rgb & 0xFF) < 90;
    }

    /**
     * @return array<string, array{int, array{int, int}, array{int, int}, array{int, int}}>
     */
    public static function pngOrientations(): array
    {
        // orientation => [dimensions attendues, [x, y] où le bloc rouge doit se trouver, [x, y] blanc de contrôle]
        return [
            '6 rotation 90 horaire : haut-gauche => haut-droite' => [6, [20, 40], [15, 5], [5, 35]],
            '8 rotation 90 anti-horaire : haut-gauche => bas-gauche' => [8, [20, 40], [5, 35], [15, 5]],
            '2 miroir horizontal : haut-gauche => haut-droite' => [2, [40, 20], [35, 5], [5, 5]],
        ];
    }

    /**
     * @param  array{int, int}  $size
     * @param  array{int, int}  $red
     * @param  array{int, int}  $white
     */
    #[DataProvider('pngOrientations')]
    public function test_png_exif_orientation_is_applied_to_generated_variants(int $orientation, array $size, array $red, array $white): void
    {
        Storage::fake('public');
        $face = Face::factory()->create(['profile_photo' => 'o.png']);
        Storage::disk('public')->put('avatars/faces/o.png', $this->markedPng(40, 20, $orientation));

        app(\App\Support\ImageVariantGenerator::class)->generate($face);

        $medium = Storage::disk('public')->path('avatars/faces/medium/o.webp');
        $this->assertSame($size, array_slice(getimagesize($medium), 0, 2));
        $this->assertTrue($this->isRedAt($medium, $red[0], $red[1]), 'le bloc rouge doit être à la position attendue');
        $this->assertFalse($this->isRedAt($medium, $white[0], $white[1]), 'le coin de contrôle doit rester blanc');
    }

    public function test_agency_logo_thumbnail_applies_the_png_exif_orientation_too(): void
    {
        Storage::fake('public');
        $agency = Producer::factory()->agency()->create(['agency_logo' => 'logo.png']);
        // 40x40, bloc rouge en haut à gauche, orientation 6 (90° horaire) : il doit finir en haut à droite.
        $im = imagecreatetruecolor(40, 40);
        imagefilledrectangle($im, 0, 0, 39, 39, (int) imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, 0, 0, 9, 9, (int) imagecolorallocate($im, 255, 0, 0));
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        Storage::disk('public')->put('logos/agencies/logo.png', substr($png, 0, 33).$this->pngChunk('eXIf', $this->exifTiff(6)).substr($png, 33));

        (new \App\Jobs\GenerateAgencyLogoThumbnail($agency->id, 'logo.png'))->handle();

        $thumb = Storage::disk('public')->path('logos/agencies/thumbnails/logo.jpg');
        $this->assertTrue($this->isRedAt($thumb, 130, 20), 'haut-droite');
        $this->assertFalse($this->isRedAt($thumb, 20, 20), 'haut-gauche redevenu blanc');
    }

    public function test_image_jobs_fail_on_timeout_and_never_retry(): void
    {
        $variants = new \App\Jobs\GenerateImageVariants('face', 1);
        $logo = new \App\Jobs\GenerateAgencyLogoThumbnail(1, 'x.png');

        foreach ([$variants, $logo] as $job) {
            $this->assertSame(60, $job->timeout);
            $this->assertTrue($job->failOnTimeout);
            $this->assertSame(1, $job->tries);
        }
    }

    /**
     * Fait échouer toute mise à jour d'un modèle (simule une panne DB) sur un dispatcher isolé.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function withFailingUpdates(string $model, \Closure $callback): void
    {
        $original = $model::getEventDispatcher();
        $isolated = new \Illuminate\Events\Dispatcher;
        $model::setEventDispatcher($isolated);
        $model::updating(function (): void {
            throw new \RuntimeException('simulated db failure');
        });

        try {
            $callback();
        } finally {
            $model::setEventDispatcher($original);
        }
    }

    public function test_face_photo_db_failure_keeps_old_files_and_removes_the_new_one(): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();
        app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $face->refresh();
        $this->seedVariants($face);
        $before = Storage::disk('public')->allFiles();
        $old = (string) $face->profile_photo;

        $this->withFailingUpdates(Face::class, function () use ($face): void {
            try {
                app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('b.jpg', $this->plainJpeg(30, 30)));
                $this->fail('exception attendue');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated db failure', $e->getMessage());
            }
        });

        $this->assertEqualsCanonicalizing($before, Storage::disk('public')->allFiles(), 'anciens fichiers intacts, nouveau fichier retiré');
        $this->assertSame($old, $face->fresh()->profile_photo);
    }

    public function test_producer_photo_db_failure_keeps_old_files_and_removes_the_new_one(): void
    {
        Storage::fake('public');
        Queue::fake();
        $producer = Producer::factory()->create();
        app(ProducerProfilePhotoService::class)->uploadProfilePhoto($producer, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $before = Storage::disk('public')->allFiles();

        $this->withFailingUpdates(Producer::class, function () use ($producer): void {
            try {
                app(ProducerProfilePhotoService::class)->uploadProfilePhoto($producer, $this->upload('b.jpg', $this->plainJpeg(30, 30)));
                $this->fail('exception attendue');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated db failure', $e->getMessage());
            }
        });

        $this->assertEqualsCanonicalizing($before, Storage::disk('public')->allFiles());
    }

    public function test_replacement_deletes_variants_claimed_by_the_job_meanwhile_and_derived_names_of_unclaimed_ones(): void
    {
        Storage::fake('public');
        Queue::fake();
        $face = Face::factory()->create();
        app(ProfilePhotoService::class)->uploadProfilePhoto($face, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $old = (string) Face::find($face->id)->profile_photo;
        $uuid = pathinfo($old, PATHINFO_FILENAME);

        // Modèle chargé AVANT que le job de variantes ne réclame ses colonnes...
        $stale = Face::find($face->id);
        foreach (['thumbnails/'.$old, 'medium/'.$uuid.'.webp', 'grid/'.$uuid.'.webp', 'large/'.$uuid.'.webp'] as $variant) {
            Storage::disk('public')->put('avatars/faces/'.$variant, 'variant');
        }
        // ... il en a réclamé deux ; grid et large existent sur disque mais leurs colonnes sont encore nulles.
        Face::whereKey($face->id)->update(['profile_photo_thumbnail' => $old, 'profile_photo_medium' => $uuid.'.webp']);
        $this->assertNull($stale->profile_photo_thumbnail);

        $result = app(ProfilePhotoService::class)->uploadProfilePhoto($stale, $this->upload('b.jpg', $this->plainJpeg(30, 30)));

        $this->assertSame(['avatars/faces/'.$result['photo']], Storage::disk('public')->allFiles(), 'plus aucun fichier de l\'ancienne photo');
    }

    public function test_producer_replacement_cleans_variants_claimed_meanwhile_and_derived_names(): void
    {
        Storage::fake('public');
        Queue::fake();
        $producer = Producer::factory()->create();
        app(ProducerProfilePhotoService::class)->uploadProfilePhoto($producer, $this->upload('a.jpg', $this->plainJpeg(40, 20)));
        $old = (string) Producer::find($producer->id)->profile_photo;
        $uuid = pathinfo($old, PATHINFO_FILENAME);
        $stale = Producer::find($producer->id);
        foreach (['thumbnails/'.$old, 'medium/'.$uuid.'.webp', 'grid/'.$uuid.'.webp', 'large/'.$uuid.'.webp'] as $variant) {
            Storage::disk('public')->put('avatars/producers/'.$variant, 'variant');
        }
        Producer::whereKey($producer->id)->update(['profile_photo_medium' => $uuid.'.webp']);

        $result = app(ProducerProfilePhotoService::class)->uploadProfilePhoto($stale, $this->upload('b.jpg', $this->plainJpeg(30, 30)));

        $this->assertSame(['avatars/producers/'.$result['photo']], Storage::disk('public')->allFiles());
    }

    public function test_presentation_video_db_failure_keeps_the_old_video_and_removes_the_new_files(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($src)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::fake('public');
        Process::fake(); // pas de vrai remux
        $face = Face::factory()->create(['presentation_video' => 'old.mp4', 'presentation_video_thumbnail' => 'old.jpg']);
        Storage::disk('public')->put('videos/faces/presentation/old.mp4', 'old');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/old.jpg', 'thumb');
        $before = Storage::disk('public')->allFiles();

        $this->withFailingUpdates(Face::class, function () use ($face, $src): void {
            try {
                app(PresentationVideoService::class)->uploadPresentationVideo($face, new UploadedFile($src, 'new.mp4', null, null, true));
                $this->fail('exception attendue');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated db failure', $e->getMessage());
            }
        });

        $this->assertEqualsCanonicalizing($before, Storage::disk('public')->allFiles(), 'ancienne vidéo intacte, nouveaux fichiers retirés');
        $this->assertSame('old.mp4', $face->fresh()->presentation_video);
    }

    public function test_presentation_video_thumbnail_failure_cleans_new_files_and_keeps_the_old_video(): void
    {
        Storage::fake('public');
        $face = Face::factory()->create(['presentation_video' => 'old.mp4', 'presentation_video_thumbnail' => 'old.jpg']);
        Storage::disk('public')->put('videos/faces/presentation/old.mp4', 'old');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/old.jpg', 'thumb');
        $before = Storage::disk('public')->allFiles();

        // Fichier vide : accepté par l'allowlist de mime, mais ffmpeg ne peut pas en extraire de miniature.
        try {
            app(PresentationVideoService::class)->uploadPresentationVideo($face, UploadedFile::fake()->create('v.mp4', 10, 'video/mp4'));
            $this->fail('exception attendue (miniature impossible)');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }

        $this->assertEqualsCanonicalizing($before, Storage::disk('public')->allFiles());
        $face = $face->fresh();
        $this->assertSame('old.mp4', $face->presentation_video);
        $this->assertSame('old.jpg', $face->presentation_video_thumbnail);
    }
}
