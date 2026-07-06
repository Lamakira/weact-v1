<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceVideo;
use App\Services\FaceMediaRetentionService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredMediaCommand extends Command
{
    protected $signature = 'faces:purge-expired-media';

    protected $description = 'Purge Face media (album photos, acting videos, UGC videos) beyond quota whose 90-day post-expiration retention window has elapsed. DB rows committed first, disk files deleted after commit; idempotent on repeat invocation.';

    public function handle(FaceMediaRetentionService $retention): int
    {
        $eligibleQuery = Face::query()
            ->whereHas('subscriptions', function ($q): void {
                $q->whereIn('status', [
                    FaceSubscriptionStatus::Expired,
                    FaceSubscriptionStatus::Cancelled,
                ]);
            });

        $totalFaces = (clone $eligibleQuery)->count();
        $this->info("Found {$totalFaces} Face(s) with at least one paid termination event.");

        $purgedPhotos = 0;
        $purgedActingVideos = 0;
        $purgedUgcVideos = 0;
        $facesPurged = 0;
        $errored = 0;

        $eligibleQuery
            ->with(['subscriptions', 'activeSubscription', 'photos', 'videos'])
            ->chunkById(100, function ($faces) use ($retention, &$purgedPhotos, &$purgedActingVideos, &$purgedUgcVideos, &$facesPurged, &$errored): void {
                foreach ($faces as $face) {
                    try {
                        $inventory = $retention->mediaPendingPurge($face);
                        $photoCount = $inventory['photos']->count();
                        $actingCount = $inventory['acting_videos']->count();
                        $ugcCount = $inventory['ugc_videos']->count();

                        if (($photoCount + $actingCount + $ugcCount) === 0) {
                            continue;
                        }

                        $until = $retention->retentionUntil($face);
                        $untilIso = $until?->toIso8601String();

                        // Spec mandate (AC #10/#11/#12 + Resolved Ambiguity #11):
                        // DB row deletion is committed FIRST inside the transaction;
                        // disk delete + per-item Log::info run ONLY after commit so a
                        // rollback never orphans disk files behind live DB rows.
                        DB::transaction(function () use ($inventory): void {
                            foreach ($inventory['photos'] as $photo) {
                                $photo->delete();
                            }
                            foreach ($inventory['acting_videos'] as $video) {
                                $video->delete();
                            }
                            foreach ($inventory['ugc_videos'] as $video) {
                                $video->delete();
                            }
                        });

                        foreach ($inventory['photos'] as $photo) {
                            $this->purgePhotoFiles($photo);
                            Log::info('Face media purged after retention window elapsed', [
                                'face_id' => $face->id,
                                'media_type' => 'album_photo',
                                'face_photo_id' => $photo->id,
                                'position' => $photo->position,
                                'retention_until' => $untilIso,
                            ]);
                            $purgedPhotos++;
                        }
                        foreach ($inventory['acting_videos'] as $video) {
                            $this->purgeVideoFiles($video);
                            Log::info('Face media purged after retention window elapsed', [
                                'face_id' => $face->id,
                                'media_type' => 'acting_video',
                                'face_video_id' => $video->id,
                                'position' => $video->position,
                                'retention_until' => $untilIso,
                            ]);
                            $purgedActingVideos++;
                        }
                        foreach ($inventory['ugc_videos'] as $video) {
                            $this->purgeVideoFiles($video);
                            Log::info('Face media purged after retention window elapsed', [
                                'face_id' => $face->id,
                                'media_type' => 'ugc_video',
                                'face_video_id' => $video->id,
                                'position' => $video->position,
                                'retention_until' => $untilIso,
                            ]);
                            $purgedUgcVideos++;
                        }

                        $facesPurged++;
                        $this->info("Purged face #{$face->id}: {$photoCount} photo(s), {$actingCount} acting video(s), {$ugcCount} ugc video(s).");
                    } catch (\Throwable $e) {
                        Log::error('Face media purge failed', [
                            'face_id' => $face->id,
                            'error_class' => $e::class,
                            'error_message' => $e->getMessage(),
                        ]);
                        $this->error("Failed to purge media for face #{$face->id}: {$e->getMessage()}");
                        $errored++;
                    }
                }
            });

        $this->info("Done. Faces purged: {$facesPurged}, Photos: {$purgedPhotos}, Acting videos: {$purgedActingVideos}, UGC videos: {$purgedUgcVideos}, Errored: {$errored}.");

        return $errored > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function purgePhotoFiles(FacePhoto $photo): void
    {
        $disk = Storage::disk('public');

        if ($photo->filename) {
            $this->deletePathOrWarn($disk, 'avatars/faces/albums/'.$photo->filename, 'album_photo', $photo->id);
        }
        if ($photo->thumbnail) {
            $this->deletePathOrWarn($disk, 'avatars/faces/albums/thumbnails/'.$photo->thumbnail, 'album_photo_thumbnail', $photo->id);
        }
        if ($photo->medium) {
            $this->deletePathOrWarn($disk, 'avatars/faces/albums/medium/'.$photo->medium, 'album_photo_medium', $photo->id);
        }
        if ($photo->grid) {
            $this->deletePathOrWarn($disk, 'avatars/faces/albums/grid/'.$photo->grid, 'album_photo_grid', $photo->id);
        }
        if ($photo->large) {
            $this->deletePathOrWarn($disk, 'avatars/faces/albums/large/'.$photo->large, 'album_photo_large', $photo->id);
        }
    }

    private function purgeVideoFiles(FaceVideo $video): void
    {
        $disk = Storage::disk('public');
        $type = $video->type->value;

        if ($video->filename) {
            $this->deletePathOrWarn($disk, "videos/faces/{$type}/".$video->filename, "{$type}_video", $video->id);
        }
        if ($video->thumbnail) {
            $this->deletePathOrWarn($disk, "videos/faces/{$type}/thumbnails/".$video->thumbnail, "{$type}_video_thumbnail", $video->id);
        }
    }

    private function deletePathOrWarn(Filesystem $disk, string $path, string $assetKind, int $modelId): void
    {
        try {
            if (! $disk->exists($path)) {
                return;
            }

            if (! $disk->delete($path)) {
                Log::warning('Disk delete returned false after retention purge — disk file may be orphaned', [
                    'path' => $path,
                    'asset_kind' => $assetKind,
                    'model_id' => $modelId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Disk delete threw after retention purge — disk file may be orphaned', [
                'path' => $path,
                'asset_kind' => $assetKind,
                'model_id' => $modelId,
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
