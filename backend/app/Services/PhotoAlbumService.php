<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;
use App\Models\FacePhoto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PhotoAlbumService
{
    public const MAX_PHOTOS = 4;

    private const STORAGE_PATH = 'avatars/faces/albums';

    private const THUMBNAIL_PATH = 'avatars/faces/albums/thumbnails';

    private const MEDIUM_PATH = 'avatars/faces/albums/medium';

    private const THUMBNAIL_SIZE = 150;

    private const MEDIUM_WIDTH = 800;

    private const QUALITY = 85;

    /**
     * Add a photo to a Face's album.
     *
     * @throws \Exception If album is full
     */
    public function addPhoto(Face $face, UploadedFile $photo): FacePhoto
    {
        // Check if face already has maximum photos
        $currentCount = $face->photos()->count();
        if ($currentCount >= self::MAX_PHOTOS) {
            throw new \Exception('Maximum '.self::MAX_PHOTOS.' photos atteint');
        }

        // Generate unique filename with UUID
        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$extension;
        $mediumFilename = pathinfo($filename, PATHINFO_FILENAME).'.webp';

        // Calculate next position
        $nextPosition = $currentCount + 1;

        // Use transaction to ensure atomicity - cleanup files on DB failure
        return DB::transaction(function () use ($face, $photo, $filename, $mediumFilename, $nextPosition) {
            // Store original photo
            Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

            try {
                // Generate thumbnail and medium
                $thumbnailFilename = $this->generateThumbnail($photo, $filename);
                $this->generateMedium($photo, $mediumFilename);

                // Create FacePhoto record
                /** @var FacePhoto $facePhoto */
                $facePhoto = FacePhoto::create([
                    'face_id' => $face->id,
                    'filename' => $filename,
                    'thumbnail' => $thumbnailFilename,
                    'medium' => $mediumFilename,
                    'position' => $nextPosition,
                ]);

                return $facePhoto;
            } catch (\Throwable $e) {
                // Clean up files on failure
                Storage::disk('public')->delete(self::STORAGE_PATH.'/'.$filename);
                Storage::disk('public')->delete(self::THUMBNAIL_PATH.'/'.$filename);
                Storage::disk('public')->delete(self::MEDIUM_PATH.'/'.$mediumFilename);
                throw $e;
            }
        });
    }

    /**
     * Delete a photo from a Face's album.
     */
    public function deletePhoto(FacePhoto $photo): bool
    {
        $face = $photo->face;

        if (! $face instanceof Face) {
            return false;
        }

        $disk = Storage::disk('public');

        // Delete files from storage
        if ($photo->filename) {
            $photoPath = self::STORAGE_PATH.'/'.$photo->filename;
            if ($disk->exists($photoPath)) {
                $disk->delete($photoPath);
            }
        }

        if ($photo->thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH.'/'.$photo->thumbnail;
            if ($disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
            }
        }

        if ($photo->medium) {
            $mediumPath = self::MEDIUM_PATH.'/'.$photo->medium;
            if ($disk->exists($mediumPath)) {
                $disk->delete($mediumPath);
            }
        }

        // Delete database record
        $photo->delete();

        // Reorder remaining photos to fill the gap
        $this->reorderAfterDelete($face);

        return true;
    }

    /**
     * Reorder photos for a Face.
     *
     * @param  array<int>  $order  Array of photo IDs in desired order
     *
     * @throws \Exception If IDs don't match Face's photos
     */
    public function reorderPhotos(Face $face, array $order): void
    {
        $photos = $face->photos()->get();
        $photoIds = $photos->pluck('id')->toArray();

        // Validate all IDs belong to this face
        foreach ($order as $id) {
            if (! in_array($id, $photoIds)) {
                throw new \Exception('Photo ID '.$id.' does not belong to this Face');
            }
        }

        // Validate all face photos are in the order array
        if (count($order) !== count($photoIds)) {
            throw new \Exception('Order array must contain all photo IDs');
        }

        // Update positions in a transaction
        DB::transaction(function () use ($order) {
            // First, set all positions to high temporary values to avoid unique constraint violations
            // Using 1000 + index as temp value (since max photos is 4, positions 1-4 are used)
            foreach ($order as $index => $photoId) {
                FacePhoto::where('id', $photoId)->update(['position' => 1000 + $index]);
            }

            // Then, set them to the correct positions
            foreach ($order as $index => $photoId) {
                FacePhoto::where('id', $photoId)->update(['position' => $index + 1]);
            }
        });
    }

    /**
     * Get all photos for a Face, ordered by position.
     *
     * @return Collection<int, FacePhoto>
     */
    public function getPhotos(Face $face): Collection
    {
        /** @var Collection<int, FacePhoto> $photos */
        $photos = $face->photos()->orderBy('position')->get();

        return $photos;
    }

    /**
     * Reorder photos after a deletion to fill the gap.
     */
    private function reorderAfterDelete(Face $face): void
    {
        $photoIds = $face->photos()->orderBy('position')->pluck('id')->toArray();

        if (empty($photoIds)) {
            return;
        }

        DB::transaction(function () use ($photoIds) {
            // First, set all to high temporary values to avoid unique constraint violations
            foreach ($photoIds as $index => $photoId) {
                FacePhoto::where('id', $photoId)->update(['position' => 1000 + $index]);
            }

            // Then, set them to the correct positions
            foreach ($photoIds as $index => $photoId) {
                FacePhoto::where('id', $photoId)->update(['position' => $index + 1]);
            }
        });
    }

    /**
     * Generate a thumbnail from the uploaded photo.
     *
     * @return string The thumbnail filename
     */
    private function generateThumbnail(UploadedFile $photo, string $filename): string
    {
        $image = Image::read($photo->getRealPath());
        $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);
        $encoded = $image->toJpeg(self::QUALITY);
        Storage::disk('public')->put(self::THUMBNAIL_PATH.'/'.$filename, $encoded->toString());

        return $filename;
    }

    private function generateMedium(UploadedFile $photo, string $mediumFilename): void
    {
        $image = Image::read($photo->getRealPath());
        $image->scaleDown(width: self::MEDIUM_WIDTH);
        $encoded = $image->toWebp(self::QUALITY);
        Storage::disk('public')->put(self::MEDIUM_PATH.'/'.$mediumFilename, $encoded->toString());
    }
}
