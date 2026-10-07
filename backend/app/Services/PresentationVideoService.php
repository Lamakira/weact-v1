<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;
use App\Support\UploadedMedia;
use App\Support\VideoMetadataStripper;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Media\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresentationVideoService
{
    private const STORAGE_PATH = 'videos/faces/presentation';

    private const THUMBNAIL_PATH = 'videos/faces/presentation/thumbnails';

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
     * Upload a presentation video for a Face and generate thumbnail.
     *
     * @return array{video: string, thumbnail: string}
     */
    public function uploadPresentationVideo(Face $face, UploadedFile $video): array
    {
        // Extension validée AVANT toute suppression : un fichier rejeté ne doit pas coûter
        // sa vidéo actuelle à l'utilisateur.
        $extension = UploadedMedia::videoExtension($video, 'video');

        $filename = Str::uuid()->toString().'.'.$extension;
        $thumbnailFilename = Str::uuid()->toString().'.jpg';
        $disk = Storage::disk('public');
        $oldPaths = [];

        // Nouveau fichier + miniature d'abord. Sur échec (écriture, ffmpeg, DB), on retire ce qui
        // vient d'être écrit et l'ancienne vidéo reste intacte (fichiers ET référence).
        try {
            if ($disk->putFileAs(self::STORAGE_PATH, $video, $filename) === false) {
                throw new \RuntimeException("Failed to store presentation video [{$filename}].");
            }

            $this->generateThumbnail(
                $disk->path(self::STORAGE_PATH.'/'.$filename),
                $thumbnailFilename
            );

            $face->refresh();
            $oldPaths = array_filter([
                $face->presentation_video ? self::STORAGE_PATH.'/'.$face->presentation_video : null,
                $face->presentation_video_thumbnail ? self::THUMBNAIL_PATH.'/'.$face->presentation_video_thumbnail : null,
            ]);

            // Une seule mise à jour vers les nouveaux noms.
            $face->update([
                'presentation_video' => $filename,
                'presentation_video_thumbnail' => $thumbnailFilename,
            ]);
        } catch (\Throwable $e) {
            $disk->delete(self::STORAGE_PATH.'/'.$filename);
            $disk->delete(self::THUMBNAIL_PATH.'/'.$thumbnailFilename);

            throw $e;
        }

        // Les anciens fichiers ne sont supprimés qu'APRÈS le succès de l'écriture DB.
        $disk->delete(array_values($oldPaths));

        $result = [
            'video' => $filename,
            'thumbnail' => $thumbnailFilename,
        ];

        // Remux sans ré-encodage (métadonnées conteneur, GPS…) APRÈS le commit : jamais
        // dans la transaction. Un échec n'invalide pas l'upload (warning loggé,
        // rattrapé par media:strip-metadata).
        VideoMetadataStripper::stripOrLog(Storage::disk('public')->path(self::STORAGE_PATH.'/'.$result['video']));

        return $result;
    }

    /**
     * Delete presentation video and thumbnail for a Face.
     */
    public function deletePresentationVideo(Face $face): bool
    {
        if (! $face->presentation_video && ! $face->presentation_video_thumbnail) {
            return false;
        }

        return DB::transaction(function () use ($face) {
            $deleted = false;
            $disk = Storage::disk('public');

            $videoPath = $face->presentation_video
                ? self::STORAGE_PATH.'/'.$face->presentation_video
                : null;
            $thumbnailPath = $face->presentation_video_thumbnail
                ? self::THUMBNAIL_PATH.'/'.$face->presentation_video_thumbnail
                : null;

            // Clear database fields first (within transaction)
            $face->update([
                'presentation_video' => null,
                'presentation_video_thumbnail' => null,
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
}
