<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ActingVideoService
{
    private const STORAGE_PATH = 'videos/faces/acting';

    private const THUMBNAIL_PATH = 'videos/faces/acting/thumbnails';

    private const MAX_SIZE_BYTES = 50 * 1024 * 1024; // 50MB

    private const MAX_DURATION_SECONDS = 120; // 2 minutes

    private FFMpeg $ffmpeg;

    private FFProbe $ffprobe;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
        ]);

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
        ]);
    }

    /**
     * Upload an acting video for a Face and generate thumbnail.
     *
     * @return array{video: string, thumbnail: string}
     */
    public function uploadActingVideo(Face $face, UploadedFile $video): array
    {
        return DB::transaction(function () use ($face, $video) {
            // Delete old video if exists
            $this->deleteActingVideo($face);

            // Generate unique filename with UUID
            $extension = $video->getClientOriginalExtension() ?: 'mp4';
            $filename = Str::uuid()->toString().'.'.$extension;
            $thumbnailFilename = Str::uuid()->toString().'.jpg';

            // Ensure storage directories exist
            $disk = Storage::disk('public');
            $disk->makeDirectory(self::STORAGE_PATH);
            $disk->makeDirectory(self::THUMBNAIL_PATH);

            // Store video using the public disk
            $disk->putFileAs(self::STORAGE_PATH, $video, $filename);

            // Generate and save thumbnail from the first frame
            try {
                $this->generateThumbnail(
                    $disk->path(self::STORAGE_PATH.'/'.$filename),
                    $thumbnailFilename
                );
            } catch (\Exception $e) {
                // Clean up uploaded video file on thumbnail generation failure
                $disk->delete(self::STORAGE_PATH.'/'.$filename);
                throw $e;
            }

            // Update Face model
            $face->update([
                'acting_video' => $filename,
                'acting_video_thumbnail' => $thumbnailFilename,
            ]);

            return [
                'video' => $filename,
                'thumbnail' => $thumbnailFilename,
            ];
        });
    }

    /**
     * Delete acting video and thumbnail for a Face.
     */
    public function deleteActingVideo(Face $face): bool
    {
        if (! $face->acting_video && ! $face->acting_video_thumbnail) {
            return false;
        }

        return DB::transaction(function () use ($face) {
            $deleted = false;
            $disk = Storage::disk('public');

            $videoPath = $face->acting_video
                ? self::STORAGE_PATH.'/'.$face->acting_video
                : null;
            $thumbnailPath = $face->acting_video_thumbnail
                ? self::THUMBNAIL_PATH.'/'.$face->acting_video_thumbnail
                : null;

            // Clear database fields first (within transaction)
            $face->update([
                'acting_video' => null,
                'acting_video_thumbnail' => null,
            ]);

            // Delete files after successful DB update
            if ($videoPath && $disk->exists($videoPath)) {
                $disk->delete($videoPath);
                $deleted = true;
            }

            if ($thumbnailPath && $disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
                $deleted = true;
            }

            return $deleted;
        });
    }

    /**
     * Generate a thumbnail from the video's first frame.
     *
     * @param  string  $videoPath  Full path to the video file
     * @param  string  $thumbnailFilename  Filename for the thumbnail
     * @return string The thumbnail filename
     */
    private function generateThumbnail(string $videoPath, string $thumbnailFilename): string
    {
        $thumbnailFullPath = Storage::disk('public')->path(self::THUMBNAIL_PATH.'/'.$thumbnailFilename);

        // Ensure the thumbnails directory exists
        $thumbnailDir = dirname($thumbnailFullPath);
        if (! is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // Open the video and extract the first frame
        /** @var Video $video */
        $video = $this->ffmpeg->open($videoPath);
        $video
            ->frame(TimeCode::fromSeconds(0))
            ->save($thumbnailFullPath);

        return $thumbnailFilename;
    }

    /**
     * Get the duration of a video file in seconds.
     *
     * @return float Duration in seconds
     */
    public function getVideoDuration(UploadedFile $video): float
    {
        $duration = $this->ffprobe
            ->format($video->getRealPath())
            ->get('duration');

        return (float) $duration;
    }

    /**
     * Get the maximum allowed video duration in seconds.
     */
    public static function getMaxDurationSeconds(): int
    {
        return self::MAX_DURATION_SECONDS;
    }

    /**
     * Get the maximum allowed video size in bytes.
     */
    public static function getMaxSizeBytes(): int
    {
        return self::MAX_SIZE_BYTES;
    }
}
