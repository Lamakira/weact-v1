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

    public function test_command_maps_only_video_and_audio_and_does_not_strip_stream_metadata(): void
    {
        $cmd = implode(' ', VideoMetadataStripper::command('/in.mp4', '/out.mp4'));

        $this->assertStringContainsString('-map 0:v -map 0:a?', $cmd);
        $this->assertStringContainsString('-map_metadata -1', $cmd);
        $this->assertStringContainsString('-c copy', $cmd);
        $this->assertStringNotContainsString('-map_metadata:s', $cmd);
        $this->assertStringNotContainsString('-map 0 ', $cmd);
    }

    public function test_strip_runs_with_a_120s_timeout_and_keeps_original_on_failure(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'boom', exitCode: 1)]);
        $path = $this->path();
        file_put_contents($path, "\0\0\0\x18ftypisom\0\0\0\0isom");

        try {
            VideoMetadataStripper::strip($path);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertStringContainsString('ftypisom', (string) file_get_contents($path));
            $this->assertFileDoesNotExist($path.'.stripped.mp4');
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
}
