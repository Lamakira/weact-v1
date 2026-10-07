<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MediaMetadataCleaner;
use App\Support\VideoMetadataStripper;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

/**
 * Remux vidéo sans métadonnées (ffmpeg réel ; sauté si ffmpeg est absent).
 */
class VideoMetadataStripperTest extends TestCase
{
    use BuildsMediaFixtures;

    private function path(): string
    {
        return tempnam(sys_get_temp_dir(), 'vms').'.mp4';
    }

    /**
     * @param  array{streams: list<array<string, mixed>>, format: array<string, mixed>}  $probe
     * @return list<string>
     */
    private function codecTypes(array $probe): array
    {
        return array_map(fn (array $s): string => (string) $s['codec_type'], $probe['streams']);
    }

    public function test_subtitle_track_and_global_location_are_removed_keeping_only_video_and_audio(): void
    {
        $path = $this->path();
        if (! $this->mp4WithSubtitleTrack($path)) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }

        $before = $this->probe($path);
        $this->assertContains('subtitle', $this->codecTypes($before));
        $this->assertArrayHasKey('location', $before['format']['tags']);
        $this->assertTrue(MediaMetadataCleaner::videoHasMetadata($path));

        VideoMetadataStripper::strip($path);

        $after = $this->probe($path);
        $this->assertEqualsCanonicalizing(['video', 'audio'], $this->codecTypes($after));
        $tags = array_keys($after['format']['tags'] ?? []);
        $this->assertNotContains('location', $tags);
        $this->assertNotContains('location-eng', $tags);
        $this->assertStringNotContainsString('6.3703', (string) file_get_contents($path));
        $this->assertFalse(MediaMetadataCleaner::videoHasMetadata($path));
    }

    public function test_rotation_display_matrix_survives_the_remux(): void
    {
        $path = $this->path();
        if (! $this->rotatedMp4($path)) {
            $this->markTestSkipped('ffmpeg indisponible ou sans -display_rotation.');
        }

        $rotation = fn (array $probe): ?float => isset($probe['streams'][0]['side_data_list'][0]['rotation'])
            ? abs((float) $probe['streams'][0]['side_data_list'][0]['rotation'])
            : null;

        $this->assertSame(90.0, $rotation($this->probe($path)), 'fixture : rotation 90 attendue');

        VideoMetadataStripper::strip($path);

        $this->assertSame(90.0, $rotation($this->probe($path)), 'la rotation doit survivre au remux');
        $this->assertArrayNotHasKey('location', $this->probe($path)['format']['tags'] ?? []);
    }

    public function test_command_maps_only_video_and_audio_and_clears_all_metadata_with_a_bare_map_metadata_minus_one(): void
    {
        $cmd = implode(' ', VideoMetadataStripper::command('/in.mp4', '/out.mp4'));

        $this->assertStringContainsString('-map 0:v -map 0:a?', $cmd);
        $this->assertStringContainsString('-map_metadata -1', $cmd);
        $this->assertStringContainsString('-c copy', $cmd);
        // `-map_metadata -1` seul efface les métadonnées globales, de flux ET de chapitres ;
        // la rotation est une side data (display matrix), pas une métadonnée.
        $this->assertStringNotContainsString('-map_metadata:s', $cmd);
        $this->assertStringNotContainsString('-map 0 ', $cmd);
    }

    public function test_strip_runs_with_a_120s_timeout_and_keeps_original_on_failure(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom'));

        try {
            VideoMetadataStripper::strip($path);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertStringContainsString('ftypisom', (string) file_get_contents($path));
            $this->assertSame([], glob($path.'.stripped.*'));
        }

        Process::assertRan(fn ($process): bool => $process->timeout === 120);
    }

    public function test_unrecognised_container_is_refused_without_running_ffmpeg(): void
    {
        Process::fake();
        $path = $this->path();
        file_put_contents($path, 'not a video');

        VideoMetadataStripper::stripOrLog($path);

        Process::assertNothingRan();
        $this->assertSame('not a video', file_get_contents($path));
    }

    private function ftyp(string $brand): string
    {
        return pack('N', 24).'ftyp'.str_pad($brand, 4).pack('N', 0).'isom';
    }

    /**
     * Fake ffmpeg qui « réussit » en créant le fichier de sortie (dernier argument).
     */
    private function fakeFfmpegWriting(string $content = 'stripped', ?\Closure $before = null): \Closure
    {
        return function ($process) use ($content, $before) {
            if ($before !== null) {
                $before($process);
            }
            $cmd = $process->command;
            file_put_contents((string) end($cmd), $content);

            return Process::result();
        };
    }

    public function test_stream_level_metadata_is_cleared_by_the_remux(): void
    {
        $mov = tempnam(sys_get_temp_dir(), 'vms').'.mov';
        if (! $this->ffmpegRun(['-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x64:rate=10', '-metadata:s:v:0', 'handler_name=SECRETHANDLER', $mov])) {
            $this->markTestSkipped('ffmpeg indisponible.');
        }
        $this->assertStringContainsString('SECRETHANDLER', (string) file_get_contents($mov));

        VideoMetadataStripper::strip($mov);

        $this->assertStringNotContainsString('SECRETHANDLER', (string) file_get_contents($mov));
    }

    public function test_successful_remux_replaces_the_original_keeps_its_mode_and_leaves_no_temp(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom').'original');
        chmod($path, 0640);
        Process::fake($this->fakeFfmpegWriting('stripped')); // sans vrai ffmpeg : couvre le chemin rename

        VideoMetadataStripper::strip($path);

        $this->assertSame('stripped', file_get_contents($path));
        clearstatcache();
        $this->assertSame(0640, fileperms($path) & 0777, 'le mode du fichier original est conservé');
        $this->assertSame([], glob($path.'.*'));
        Process::assertRan(fn ($process): bool => in_array('-map_metadata', $process->command, true) && in_array($path, $process->command, true));
    }

    public function test_temp_file_names_are_unique_per_call(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom'));
        $outputs = [];
        Process::fake($this->fakeFfmpegWriting($this->ftyp('isom'), function ($process) use (&$outputs): void {
            $cmd = $process->command;
            $outputs[] = end($cmd);
        }));

        VideoMetadataStripper::strip($path);
        VideoMetadataStripper::strip($path);

        $this->assertCount(2, array_unique($outputs));
        $this->assertNotSame($path.'.stripped.mp4', $outputs[0], 'nom aléatoire, pas déterministe');
        $this->assertStringStartsWith($path.'.', (string) $outputs[0]);
    }

    public function test_original_deleted_during_the_remux_is_not_resurrected(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom'));
        Process::fake($this->fakeFfmpegWriting('stripped', fn () => unlink($path)));

        try {
            VideoMetadataStripper::strip($path);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($path);
            $this->assertSame([], glob($path.'.*'), 'le temporaire est supprimé');
        }
    }

    public function test_original_replaced_during_the_remux_is_left_alone(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom').'v1');
        Process::fake($this->fakeFfmpegWriting('stripped-v1', function () use ($path): void {
            unlink($path);
            file_put_contents($path, $this->ftyp('isom').'NEWER-UPLOAD'); // ré-upload concurrent
        }));

        try {
            VideoMetadataStripper::strip($path);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertStringContainsString('NEWER-UPLOAD', (string) file_get_contents($path));
            $this->assertSame([], glob($path.'.*'));
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function isoBmffLayouts(): array
    {
        return [
            'free puis ftyp' => ['free-ftyp'],
            'wide puis mdat' => ['wide-mdat'],
            'skip puis moov' => ['skip-moov'],
            'moov seul' => ['moov'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('isoBmffLayouts')]
    public function test_container_detection_scans_the_first_top_level_atoms(string $layout): void
    {
        $atom = fn (string $type, string $body = "\0\0\0\0"): string => pack('N', 8 + strlen($body)).$type.$body;
        $bytes = match ($layout) {
            'free-ftyp' => $atom('free', str_repeat("\0", 16)).$this->ftyp('isom'),
            'wide-mdat' => $atom('wide').$atom('mdat', 'data'),
            'skip-moov' => $atom('skip').$atom('moov', 'data'),
            default => $atom('moov', 'data'),
        };
        $path = $this->path();
        file_put_contents($path, $bytes);

        $this->assertNotNull(VideoMetadataStripper::format($path));
        $this->assertSame('mov', VideoMetadataStripper::format($this->writeTmp($this->ftyp('qt  '))));
        $this->assertNull(VideoMetadataStripper::format($this->writeTmp('plain text, not a video at all')));
    }

    private function writeTmp(string $bytes): string
    {
        $path = $this->path();
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * Faux ffmpeg/ffprobe : le 1er ffmpeg échoue ; ffprobe renvoie les flux donnés ; le 2e ffmpeg réussit.
     *
     * @param  list<array{index: int, codec_type: string, codec_name: string}>  $streams
     * @param  list<string>  $ffmpegCommands  alimenté avec les commandes ffmpeg reçues
     */
    private function fakeProbeFallback(array $streams, array &$ffmpegCommands, bool $secondSucceeds = true): \Closure
    {
        return function ($process) use ($streams, &$ffmpegCommands, $secondSucceeds) {
            $cmd = $process->command;
            if (str_contains((string) $cmd[0], 'ffprobe')) {
                return Process::result(output: json_encode(['streams' => $streams]));
            }
            $ffmpegCommands[] = implode(' ', $cmd);
            if (count($ffmpegCommands) === 1 || ! $secondSucceeds) {
                return Process::result(errorOutput: 'Could not find tag for codec none', exitCode: 1);
            }
            file_put_contents((string) end($cmd), 'stripped-fallback');

            return Process::result();
        };
    }

    public function test_copy_failure_falls_back_to_mapping_only_allowlisted_audio_streams_without_reencoding(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom').'original');
        $commands = [];
        Process::fake($this->fakeProbeFallback([
            ['index' => 0, 'codec_type' => 'video', 'codec_name' => 'hevc'],
            ['index' => 1, 'codec_type' => 'audio', 'codec_name' => 'aac'],
            ['index' => 2, 'codec_type' => 'audio', 'codec_name' => 'none'], // iPhone spatial audio (apac)
            ['index' => 3, 'codec_type' => 'data', 'codec_name' => 'none'],
        ], $commands));

        VideoMetadataStripper::strip($path);

        $this->assertCount(2, $commands);
        $this->assertStringContainsString('-map 0:v -map 0:a? ', $commands[0]);
        $this->assertStringContainsString('-map 0:v -map 0:1 ', $commands[1]);
        $this->assertStringNotContainsString('0:2', $commands[1], 'flux audio non allowlisté écarté');
        $this->assertStringNotContainsString('0:3', $commands[1]);
        $this->assertStringContainsString('-c copy', $commands[1]);
        $this->assertStringNotContainsString('-c:a', $commands[1], 'aucun ré-encodage audio');
        $this->assertStringContainsString('-map_metadata -1', $commands[1]);
        $this->assertSame('stripped-fallback', file_get_contents($path));
        $this->assertSame([], glob($path.'.*'));
    }

    public function test_no_allowlisted_audio_keeps_the_original_and_logs_a_warning_instead_of_dropping_all_audio(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom').'original');
        $commands = [];
        Process::fake($this->fakeProbeFallback([
            ['index' => 0, 'codec_type' => 'video', 'codec_name' => 'hevc'],
            ['index' => 1, 'codec_type' => 'audio', 'codec_name' => 'none'],
        ], $commands));
        \Illuminate\Support\Facades\Log::spy();

        VideoMetadataStripper::stripOrLog($path);

        $this->assertCount(1, $commands, 'pas de second ffmpeg : on ne supprime jamais tout l\'audio en silence');
        $this->assertStringContainsString('original', (string) file_get_contents($path));
        $this->assertSame([], glob($path.'.*'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
    }

    public function test_when_the_fallback_ffmpeg_also_fails_the_original_is_kept_and_a_warning_logged(): void
    {
        $path = $this->path();
        file_put_contents($path, $this->ftyp('isom').'original');
        $commands = [];
        Process::fake($this->fakeProbeFallback([
            ['index' => 0, 'codec_type' => 'video', 'codec_name' => 'h264'],
            ['index' => 1, 'codec_type' => 'audio', 'codec_name' => 'aac'],
        ], $commands, false));
        \Illuminate\Support\Facades\Log::spy();

        VideoMetadataStripper::stripOrLog($path);

        $this->assertCount(2, $commands);
        $this->assertStringContainsString('original', (string) file_get_contents($path));
        $this->assertSame([], glob($path.'.*'));
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
    }

    /**
     * @param  list<string>  $args
     */
    private function ffmpegRun(array $args): bool
    {
        $bin = (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg');
        if (! is_executable($bin)) {
            return false;
        }
        exec(escapeshellarg($bin).' -y -v error '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1', $out, $code);

        return $code === 0;
    }
}
