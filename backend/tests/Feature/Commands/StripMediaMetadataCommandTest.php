<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Support\ImageMetadataStripper;
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
        $this->assertTrue(ImageMetadataStripper::isDirty($full));
    }

    public function test_apply_cleans_in_place_keeps_orientation_tag_and_second_run_skips(): void
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
            $exif = exif_read_data($path);
            $this->assertSame(6, $exif['Orientation'], 'orientation conservée dans un EXIF minimal');
            $this->assertArrayNotHasKey('GPSLatitude', $exif);
            $this->assertArrayNotHasKey('Make', $exif);
            $this->assertFalse(ImageMetadataStripper::isDirty($path));
            $this->assertSame([40, 20], array_slice(getimagesize($path), 0, 2), 'aucun ré-encodage : pixels intacts');
        }
        Storage::disk('public')->assertExists('avatars/faces/a.jpg'); // même chemin, même nom
        $this->assertSame($variantHash, hash_file('sha256', $variant));
        $this->assertSame([], glob(dirname($public).'/*.tmp'));
        $this->assertSame([], glob(dirname($public).'/*.stripping.tmp'));

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

        $this->assertTrue(ImageMetadataStripper::isDirty($producer), '--path exclut avatars/producers');
        $this->assertSame(1, (int) ! ImageMetadataStripper::isDirty($a) + (int) ! ImageMetadataStripper::isDirty($b), '--limit=1');
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
        $this->assertFalse(ImageMetadataStripper::isDirty($good));
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

    public function test_files_are_selected_by_magic_bytes_and_unknown_formats_are_reported(): void
    {
        // Extensions atypiques : sélectionnés par leurs octets magiques.
        $jfif = $this->putDirtyJpeg('public', 'avatars/faces/legacy.jfif');
        $noExt = $this->putDirtyJpeg('public', 'avatars/faces/noext');
        Storage::disk('public')->put('avatars/faces/notes.txt', 'not an image');

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('2 nettoyé(s), 0 échec(s), 1 ignoré(s) (format inconnu)')
            ->assertExitCode(0);

        $this->assertFalse(ImageMetadataStripper::isDirty($jfif));
        $this->assertFalse(ImageMetadataStripper::isDirty($noExt));
        $this->assertSame('not an image', Storage::disk('public')->get('avatars/faces/notes.txt'));
    }

    public function test_stale_temp_files_are_ignored_and_removed_only_when_older_than_one_hour(): void
    {
        $dir = 'avatars/faces';
        Storage::disk('public')->put($dir.'/old.jpg.stripping.tmp', 'partial');
        Storage::disk('public')->put($dir.'/new.jpg.stripping.tmp', 'partial');
        Storage::disk('public')->put($dir.'/old.mp4.stripped.mp4', 'partial');
        $old = Storage::disk('public')->path($dir.'/old.jpg.stripping.tmp');
        $oldVideo = Storage::disk('public')->path($dir.'/old.mp4.stripped.mp4');
        $new = Storage::disk('public')->path($dir.'/new.jpg.stripping.tmp');
        touch($old, time() - 7200);
        touch($oldVideo, time() - 7200);

        // Dry run : rien supprimé, et jamais compté comme média.
        $this->artisan('media:strip-metadata')->expectsOutputToContain('0 scanné(s)')->assertExitCode(0);
        $this->assertFileExists($old);

        $this->artisan('media:strip-metadata', ['--apply' => true])
            ->expectsOutputToContain('2 temporaire(s) périmé(s) supprimé(s)')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist($oldVideo);
        $this->assertFileExists($new, 'un temporaire récent peut appartenir à un run en cours');
    }

    public function test_failures_do_not_consume_limit_slots_and_are_listed(): void
    {
        $tiff = "II*\0".pack('V', 8).pack('v', 0).pack('V', 0);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
        // 3 fichiers « cassés » (APP1 puis octets sans SOS) triés AVANT les bons.
        foreach (['a1', 'a2', 'a3'] as $name) {
            Storage::disk('public')->put("avatars/faces/{$name}.jpg", "\xFF\xD8".$app1.str_repeat('garbage', 5));
        }
        $good1 = $this->putDirtyJpeg('public', 'avatars/faces/b1.jpg');
        $good2 = $this->putDirtyJpeg('public', 'avatars/faces/b2.jpg');
        $good3 = $this->putDirtyJpeg('public', 'avatars/faces/b3.jpg');

        $this->artisan('media:strip-metadata', ['--apply' => true, '--limit' => 2])
            ->expectsOutputToContain('2 nettoyé(s), 3 échec(s)')
            ->expectsOutputToContain('avatars/faces/a1.jpg')
            ->assertExitCode(1);

        $this->assertFalse(ImageMetadataStripper::isDirty($good1));
        $this->assertFalse(ImageMetadataStripper::isDirty($good2));
        $this->assertTrue(ImageMetadataStripper::isDirty($good3), '--limit=2 : le 3e bon fichier attend le prochain lot');
    }

    public function test_trailing_data_after_eoi_is_picked_up_by_the_retro_command(): void
    {
        $path = Storage::disk('public')->path('avatars/faces/motion.jpg');
        // Base PROPRE (le JPEG GD porte un COM « gd-jpeg », sale d'office) : les octets
        // après l'EOI sont alors l'unique raison pour laquelle le fichier est « sale ».
        $base = tempnam(sys_get_temp_dir(), 'base');
        file_put_contents($base, $this->plainJpeg(8, 8));
        ImageMetadataStripper::strip($base, $base.'.clean');
        $this->assertFalse(ImageMetadataStripper::isDirty($base.'.clean'));
        Storage::disk('public')->put('avatars/faces/motion.jpg', file_get_contents($base.'.clean')."\0\0\0\x18ftypmp42 SECRET");

        $this->artisan('media:strip-metadata', ['--apply' => true])->expectsOutputToContain('1 nettoyé(s)')->assertExitCode(0);

        $this->assertStringNotContainsString('SECRET', (string) file_get_contents($path));
    }
}
