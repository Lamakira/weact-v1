<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Producer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ProducerProfilePhotoService
{
    private const STORAGE_PATH = 'avatars/producers';
    private const THUMBNAIL_PATH = 'avatars/producers/thumbnails';
    private const THUMBNAIL_SIZE = 150;
    private const THUMBNAIL_QUALITY = 85;

    /**
     * Upload a profile photo for a Producer and generate thumbnail.
     *
     * @param Producer $producer
     * @param UploadedFile $photo
     * @return array{photo: string, thumbnail: string}
     */
    public function uploadProfilePhoto(Producer $producer, UploadedFile $photo): array
    {
        // Delete old photos if they exist
        $this->deleteProfilePhoto($producer);

        // Generate unique filename with UUID
        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        // Store original photo using the public disk
        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

        // Generate and save thumbnail from the uploaded file directly
        $thumbnailFilename = $this->generateThumbnail($photo, $filename);

        // Update Producer model
        $producer->update([
            'profile_photo' => $filename,
            'profile_photo_thumbnail' => $thumbnailFilename,
        ]);

        return [
            'photo' => $filename,
            'thumbnail' => $thumbnailFilename,
        ];
    }

    /**
     * Delete profile photo and thumbnail for a Producer.
     *
     * @param Producer $producer
     * @return bool
     */
    public function deleteProfilePhoto(Producer $producer): bool
    {
        $deleted = false;
        $disk = Storage::disk('public');

        if ($producer->profile_photo) {
            $photoPath = self::STORAGE_PATH . '/' . $producer->profile_photo;
            if ($disk->exists($photoPath)) {
                $disk->delete($photoPath);
                $deleted = true;
            }
        }

        if ($producer->profile_photo_thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH . '/' . $producer->profile_photo_thumbnail;
            if ($disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
                $deleted = true;
            }
        }

        // Clear the database fields
        if ($producer->profile_photo || $producer->profile_photo_thumbnail) {
            $producer->update([
                'profile_photo' => null,
                'profile_photo_thumbnail' => null,
            ]);
        }

        return $deleted;
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

        // Store thumbnail using public disk (works with fake storage in tests)
        Storage::disk('public')->put(self::THUMBNAIL_PATH . '/' . $filename, $encoded->toString());

        return $filename;
    }
}
