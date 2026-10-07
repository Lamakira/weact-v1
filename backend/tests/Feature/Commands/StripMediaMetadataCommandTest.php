<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Support\MediaMetadataCleaner;
use App\Support\VideoMetadataStripper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

/**
 * media:strip-metadata — rétrofit EXIF/GPS (images) et métadonnées conteneur
 * (vidéos) des fichiers déjà stockés. Vrais fichiers fixtures (GD + ffmpeg).
 */
class StripMediaMetadataCommandTest extends TestCase
{
    use BuildsMediaFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Log::spy();
    }

    private function putDirtyJpeg(string $disk, string $path, int $orientation = 6): string
    {
        Storage::disk($disk)->put($path, $this->jpegWithExif(40, 20, $orientation));

        return Storage::disk($disk)->path($path);
    }

    public function test_dry_run_reports_but_modifies_nothing(): void
    {
        $full = $this->putDirtyJpeg('public', 'avatars/faces/a.jpg');
        $before = hash_file('sha256', $full);

        $this->artisan('media:strip-metadata')
            ->expectsOutputToContain('1 avec métadonnées, 1 à nettoyer')
            ->assertExitCode(0);

        $this->assertSame($before, hash_file('sha256', $full));
        $this->assertTrue(MediaMetadataCleaner::imageHasMetadata($full));
    }

    public function test_apply_cleans_in_place_bakes_orientation_and_second_run_skips(): void
    {
        $public = $this->putDirtyJpeg('public', 'avatars/faces/a.jpg');
        $private = $this->putDirtyJpeg('local', 'products/p.jpg');
        // Une variante (sous-dossier) et un fichier propre ne sont pas touchés.
        $variant = $this->putDirtyJpeg('public', 'avatars/faces/thumbnails/a.jpg');
        $variantHash = hash_file('sha256', $variant);

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('2 nettoyé(s), 0 échec(s)')
            ->assertExitCode(0);

        foreach ([$public, $private] as $path) {
            $exif = @exif_read_data($path);
            $this->assertFalse(is_array($exif) && isset($exif['GPSLatitude']) || is_array($exif) && isset($exif['Orientation']));
            $this->assertFalse(MediaMetadataCleaner::imageHasMetadata($path));
            [$w, $h] = getimagesize($path);
            $this->assertSame([20, 40], [$w, $h], 'orientation 6 doit être appliquée aux pixels');
        }
        Storage::disk('public')->assertExists('avatars/faces/a.jpg'); // même chemin, même nom
        $this->assertSame($variantHash, hash_file('sha256', $variant));
        $this->assertSame([], glob(dirname($public).'/*.tmp'));

        $cleanHash = hash_file('sha256', $public);

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('0 avec métadonnées, 0 nettoyé(s)')
            ->assertExitCode(0);

        $this->assertSame($cleanHash, hash_file('sha256', $public));
    }

    public function test_limit_and_path_restrict_the_batch(): void
    {
        $a = $this->putDirtyJpeg('public', 'avatars/faces/a.jpg');
        $b = $this->putDirtyJpeg('public', 'avatars/faces/b.jpg');
        $producer = $this->putDirtyJpeg('public', 'avatars/producers/p.jpg');

        $this->artisan('media:strip-metadata', ['--apply' => true, '--limit' => 1, '--path' => 'avatars/faces'])
            ->expectsOutputToContain('1 nettoyé(s)')
            ->assertExitCode(0);

        $this->assertTrue(MediaMetadataCleaner::imageHasMetadata($producer), '--path exclut avatars/producers');
        $this->assertSame(1, (int) ! MediaMetadataCleaner::imageHasMetadata($a) + (int) ! MediaMetadataCleaner::imageHasMetadata($b), '--limit=1');
    }

    public function test_undecodable_image_is_left_intact_counted_and_batch_continues(): void
    {
        $tiff = "II*\0".pack('V', 8).pack('v', 0).pack('V', 0);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
        Storage::disk('public')->put('avatars/faces/a-broken.jpg', "\xFF\xD8".$app1.str_repeat('garbage', 10));
        $broken = Storage::disk('public')->path('avatars/faces/a-broken.jpg');
        $brokenHash = hash_file('sha256', $broken);
        $good = $this->putDirtyJpeg('public', 'avatars/faces/b-good.jpg');

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('1 nettoyé(s), 1 échec(s)')
            ->assertExitCode(1);

        $this->assertSame($brokenHash, hash_file('sha256', $broken));
        $this->assertFalse(MediaMetadataCleaner::imageHasMetadata($good));
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_video_is_remuxed_in_place_then_skipped(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($path)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::disk('public')->put('videos/faces/presentation/v.mp4', (string) file_get_contents($path));
        $full = Storage::disk('public')->path('videos/faces/presentation/v.mp4');
        $this->assertTrue(MediaMetadataCleaner::videoHasMetadata($full));

        $this->artisan('media:strip-metadata')->expectsOutputToContain('1 à nettoyer')->assertExitCode(0);
        $this->assertTrue(MediaMetadataCleaner::videoHasMetadata($full), 'dry run : inchangé');

        $this->artisan('media:strip-metadata', ['--apply' => true])->expectsOutputToContain('1 nettoyé(s)')->assertExitCode(0);
        $this->assertFalse(MediaMetadataCleaner::videoHasMetadata($full));
        $this->assertGreaterThan(0, filesize($full));

        $this->artisan('media:strip-metadata', ['--apply' => true])->expectsOutputToContain('0 avec métadonnées')->assertExitCode(0);
    }

    public function test_failing_remux_leaves_original_intact(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        if (! $this->mp4WithLocation($path)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        Storage::disk('local')->put('ugc/deliverables/unboxing/v.mp4', (string) file_get_contents($path));
        $full = Storage::disk('local')->path('ugc/deliverables/unboxing/v.mp4');
        $before = hash_file('sha256', $full);

        // Seul le remux (ffmpeg -map_metadata) échoue ; ffprobe tourne pour de vrai.
        Process::fake(['*-map_metadata*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('0 nettoyé(s), 1 échec(s)')
            ->assertExitCode(1);

        $this->assertSame($before, hash_file('sha256', $full));
        $this->assertSame([], glob(dirname($full).'/*.stripped.*'));
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_strip_or_log_never_throws_and_keeps_original_when_remux_fails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        file_put_contents($path, 'original');
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);

        VideoMetadataStripper::stripOrLog($path);

        $this->assertSame('original', file_get_contents($path));
        $this->assertFileDoesNotExist($path.'.stripped.mp4');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => ($context['path'] ?? null) === $path)->once();
    }
}
