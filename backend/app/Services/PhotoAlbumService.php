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
    private const THUMBNAIL_SIZE = 150;
    private const THUMBNAIL_QUALITY = 85;

    /**
     * Add a photo to a Face's album.
     *
     * @param Face $face
     * @param UploadedFile $photo
     * @return FacePhoto
     * @throws \Exception If album is full
     */
    public function addPhoto(Face $face, UploadedFile $photo): FacePhoto
    {
        // Check if face already has maximum photos
        $currentCount = $face->photos()->count();
        if ($currentCount >= self::MAX_PHOTOS) {
            throw new \Exception('Maximum ' . self::MAX_PHOTOS . ' photos atteint');
        }

        // Generate unique filename with UUID
        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        // Calculate next position
        $nextPosition = $currentCount + 1;

        // Use transaction to ensure atomicity - cleanup files on DB failure
        return DB::transaction(function () use ($face, $photo, $filename, $nextPosition) {
            // Store original photo
            Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

            try {
                // Generate and save thumbnail
                $thumbnailFilename = $this->generateThumbnail($photo, $filename);

                // Create FacePhoto record
                return FacePhoto::create([
                    'face_id' => $face->id,
                    'filename' => $filename,
                    'thumbnail' => $thumbnailFilename,
                    'position' => $nextPosition,
                ]);
            } catch (\Throwable $e) {
                // Clean up files on failure
                Storage::disk('public')->delete(self::STORAGE_PATH . '/' . $filename);
                Storage::disk('public')->delete(self::THUMBNAIL_PATH . '/' . $filename);
                throw $e;
            }
        });
    }

    /**
     * Delete a photo from a Face's album.
     *
     * @param FacePhoto $photo
     * @return bool
     */
    public function deletePhoto(FacePhoto $photo): bool
    {
        $face = $photo->face;
        $disk = Storage::disk('public');

        // Delete files from storage
        if ($photo->filename) {
            $photoPath = self::STORAGE_PATH . '/' . $photo->filename;
            if ($disk->exists($photoPath)) {
                $disk->delete($photoPath);
            }
        }

        if ($photo->thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH . '/' . $photo->thumbnail;
            if ($disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
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
     * @param Face $face
     * @param array<int> $order Array of photo IDs in desired order
     * @return void
     * @throws \Exception If IDs don't match Face's photos
     */
    public function reorderPhotos(Face $face, array $order): void
    {
        $photos = $face->photos()->get();
        $photoIds = $photos->pluck('id')->toArray();

        // Validate all IDs belong to this face
        foreach ($order as $id) {
            if (!in_array($id, $photoIds)) {
                throw new \Exception('Photo ID ' . $id . ' does not belong to this Face');
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
     * @param Face $face
     * @return Collection<int, FacePhoto>
     */
    public function getPhotos(Face $face): Collection
    {
        return $face->photos()->orderBy('position')->get();
    }

    /**
     * Reorder photos after a deletion to fill the gap.
     *
     * @param Face $face
     * @return void
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
     * @param UploadedFile $photo
     * @param string $filename
     * @return string The thumbnail filename
     */
    private function generateThumbnail(UploadedFile $photo, string $filename): string
    {
        // Read the original image from the uploaded file's temp path
        $image = Image::read($photo->getRealPath());
        $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

        // Convert to JPEG and get the encoded data
        $encoded = $image->toJpeg(self::THUMBNAIL_QUALITY);

        // Store thumbnail using public disk
        Storage::disk('public')->put(self::THUMBNAIL_PATH . '/' . $filename, $encoded->toString());

        return $filename;
    }
}
