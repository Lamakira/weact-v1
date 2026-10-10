<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MediaMetadataCleaner;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Détection vidéo « à nettoyer » à partir de sorties ffprobe JSON (sans vrai ffprobe).
 */
class MediaMetadataCleanerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $json
     */
    private function probeReturns(array $json): bool
    {
        Process::fake(['*' => Process::result(output: json_encode($json))]);

        return MediaMetadataCleaner::videoHasMetadata('/some/video.mp4');
    }

    public function test_a_clean_video_with_only_structural_tags_is_not_flagged(): void
    {
        $this->assertFalse($this->probeReturns([
            'streams' => [
                ['codec_type' => 'video', 'disposition' => ['attached_pic' => 0]],
                ['codec_type' => 'audio', 'disposition' => ['attached_pic' => 0]],
            ],
            'format' => ['tags' => ['major_brand' => 'isom', 'minor_version' => '512', 'compatible_brands' => 'isomiso2', 'encoder' => 'Lavf']],
        ]));
    }

    public function test_a_video_still_carrying_cover_art_is_flagged(): void
    {
        $this->assertTrue($this->probeReturns([
            'streams' => [
                ['codec_type' => 'video', 'disposition' => ['attached_pic' => 0]],
                ['codec_type' => 'video', 'disposition' => ['attached_pic' => 1]], // pochette JPEG avec son propre EXIF
                ['codec_type' => 'audio', 'disposition' => ['attached_pic' => 0]],
            ],
            'format' => ['tags' => ['major_brand' => 'isom']],
        ]));
    }

    public function test_ffprobe_is_asked_for_the_attached_pic_disposition(): void
    {
        $this->probeReturns(['streams' => [], 'format' => []]);

        Process::assertRan(fn ($process): bool => str_contains(implode(' ', $process->command), 'stream_disposition=attached_pic'));
    }
}
