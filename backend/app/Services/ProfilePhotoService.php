<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ProfilePhotoService
{
    private const STORAGE_PATH = 'avatars/faces';
    private const THUMBNAIL_PATH = 'avatars/faces/thumbnails';
    private const THUMBNAIL_SIZE = 150;
    private const THUMBNAIL_QUALITY = 85;

    /**
     * Upload a profile photo for a Face and generate thumbnail.
     *
     * @param Face $face
     * @param UploadedFile $photo
     * @return array{photo: string, thumbnail: string}
     */
    public function uploadProfilePhoto(Face $face, UploadedFile $photo): array
    {
        // Delete old photos if they exist
        $this->deleteProfilePhoto($face);

        // Generate unique filename with UUID
        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        // Store original photo using the public disk
        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

        // Generate and save thumbnail from the uploaded file directly
        $thumbnailFilename = $this->generateThumbnail($photo, $filename);

        // Update Face model
        $face->update([
            'profile_photo' => $filename,
            'profile_photo_thumbnail' => $thumbnailFilename,
        ]);

        return [
            'photo' => $filename,
            'thumbnail' => $thumbnailFilename,
        ];
    }

    /**
     * Delete profile photo and thumbnail for a Face.
     *
     * @param Face $face
     * @return bool
     */
    public function deleteProfilePhoto(Face $face): bool
    {
        $deleted = false;
        $disk = Storage::disk('public');

        if ($face->profile_photo) {
            $photoPath = self::STORAGE_PATH . '/' . $face->profile_photo;
            if ($disk->exists($photoPath)) {
                $disk->delete($photoPath);
                $deleted = true;
            }
        }

        if ($face->profile_photo_thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH . '/' . $face->profile_photo_thumbnail;
            if ($disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
                $deleted = true;
            }
        }

        // Clear the database fields
        if ($face->profile_photo || $face->profile_photo_thumbnail) {
            $face->update([
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
