<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceVideoType;
use App\Exceptions\VideoQuotaReachedException;
use App\Models\Face;
use App\Models\FaceVideo;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FaceVideoService
{
    private const MAX_DURATION_SECONDS = 120;

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
     * Append a typed portfolio video, enforcing the per-tier per-type quota.
     *
     * @throws VideoQuotaReachedException If the Face's tier quota for $type is reached.
     */
    public function uploadVideo(Face $face, FaceVideoType $type, UploadedFile $video): FaceVideo
    {
        $extension = $video->getClientOriginalExtension() ?: 'mp4';
        $filename = Str::uuid()->toString().'.'.$extension;
        $thumbnailFilename = Str::uuid()->toString().'.jpg';
        $storagePath = $this->storagePath($type);
        $thumbnailPath = $this->thumbnailPath($type);

        return DB::transaction(function () use ($face, $type, $video, $filename, $thumbnailFilename, $storagePath, $thumbnailPath): FaceVideo {
            // Re-check the per-type quota inside the transaction, before any
            // filesystem write, to close the race with the FormRequest guard.
            // lockForUpdate serializes concurrent same-type uploads so two
            // within-quota uploads cannot both claim the same next position.
            $limit = $this->typeQuota($face, $type);
            $currentCount = $face->videos()->where('type', $type)->lockForUpdate()->count();
            if ($currentCount >= $limit) {
                throw new VideoQuotaReachedException($limit, $type);
            }

            $nextPosition = $currentCount + 1;

            $disk = Storage::disk('public');
            $disk->makeDirectory($storagePath);
            $disk->makeDirectory($thumbnailPath);

            try {
                $disk->putFileAs($storagePath, $video, $filename);

                $this->generateThumbnail(
                    $disk->path($storagePath.'/'.$filename),
                    $thumbnailPath,
                    $thumbnailFilename,
                );

                /** @var FaceVideo $faceVideo */
                $faceVideo = FaceVideo::create([
                    'face_id' => $face->id,
                    'type' => $type,
                    'filename' => $filename,
                    'thumbnail' => $thumbnailFilename,
                    'position' => $nextPosition,
                ]);

                return $faceVideo;
            } catch (\Throwable $e) {
                $disk->delete($storagePath.'/'.$filename);
                $disk->delete($thumbnailPath.'/'.$thumbnailFilename);

                // A unique(face_id, type, position) collision means a concurrent
                // upload won the race between this transaction's count() and its
                // insert — surface it as the contracted quota response, not a 500.
                if ($e instanceof UniqueConstraintViolationException) {
                    throw new VideoQuotaReachedException($limit, $type);
                }

                throw $e;
            }
        });
    }

    /**
     * Delete one portfolio video (files + DB row) and close the position gap
     * for the remaining videos of the same type.
     */
    public function deleteVideo(FaceVideo $video): bool
    {
        $face = $video->face;

        if (! $face instanceof Face) {
            return false;
        }

        $type = $video->type;
        $disk = Storage::disk('public');
        $filename = $video->filename;
        $thumbnail = $video->thumbnail;

        DB::transaction(function () use ($video, $face, $type): void {
            $video->delete();
            $this->reorderAfterDelete($face, $type);
        });

        // Filesystem cleanup runs only after the DB mutation has committed, so
        // a rolled-back transaction never leaves a live row pointing at a
        // missing file.
        if ($filename) {
            $disk->delete($this->storagePath($type).'/'.$filename);
        }
        if ($thumbnail) {
            $disk->delete($this->thumbnailPath($type).'/'.$thumbnail);
        }

        return true;
    }

    public function getVideoDuration(UploadedFile $video): float
    {
        return (float) $this->ffprobe
            ->format($video->getRealPath())
            ->get('duration');
    }

    public static function getMaxDurationSeconds(): int
    {
        return self::MAX_DURATION_SECONDS;
    }

    private function typeQuota(Face $face, FaceVideoType $type): int
    {
        $capabilities = app(FaceEntitlementService::class)->capabilities($face);

        return $type === FaceVideoType::Acting
            ? $capabilities->maxActingVideos
            : $capabilities->maxUgcVideos;
    }

    private function storagePath(FaceVideoType $type): string
    {
        return 'videos/faces/'.$type->value;
    }

    private function thumbnailPath(FaceVideoType $type): string
    {
        return 'videos/faces/'.$type->value.'/thumbnails';
    }

    private function generateThumbnail(string $videoPath, string $thumbnailPath, string $thumbnailFilename): void
    {
        $thumbnailFullPath = Storage::disk('public')->path($thumbnailPath.'/'.$thumbnailFilename);
        $thumbnailDir = dirname($thumbnailFullPath);
        if (! is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        /** @var Video $video */
        $video = $this->ffmpeg->open($videoPath);
        $video
            ->frame(TimeCode::fromSeconds(0))
            ->save($thumbnailFullPath);
    }

    /**
     * Settle the same-type videos to a contiguous 1..N sequence after a delete.
     * Two-pass offset reorder dodges the unique(face_id, type, position) index.
     */
    private function reorderAfterDelete(Face $face, FaceVideoType $type): void
    {
        $videoIds = $face->videos()
            ->where('type', $type)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        if ($videoIds === []) {
            return;
        }

        foreach ($videoIds as $index => $id) {
            FaceVideo::where('id', $id)->update(['position' => 1000 + $index]);
        }
        foreach ($videoIds as $index => $id) {
            FaceVideo::where('id', $id)->update(['position' => $index + 1]);
        }
    }
}
