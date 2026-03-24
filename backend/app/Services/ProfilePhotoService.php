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
    private const MEDIUM_PATH = 'avatars/faces/medium';
    private const THUMBNAIL_SIZE = 150;
    private const MEDIUM_WIDTH = 800;
    private const QUALITY = 85;

    /**
     * Upload a profile photo for a Face and generate thumbnail + medium.
     *
     * @param Face $face
     * @param UploadedFile $photo
     * @return array{photo: string, thumbnail: string, medium: string}
     */
    public function uploadProfilePhoto(Face $face, UploadedFile $photo): array
    {
        // Delete old photos if they exist
        $this->deleteProfilePhoto($face);

        // Generate unique filename with UUID
        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;
        $mediumFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';

        // Store original photo using the public disk
        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

        // Generate thumbnail and medium from the uploaded file
        $thumbnailFilename = $this->generateThumbnail($photo, $filename);
        $this->generateMedium($photo, $mediumFilename);

        // Update Face model
        $face->update([
            'profile_photo' => $filename,
            'profile_photo_thumbnail' => $thumbnailFilename,
            'profile_photo_medium' => $mediumFilename,
        ]);

        return [
            'photo' => $filename,
            'thumbnail' => $thumbnailFilename,
            'medium' => $mediumFilename,
        ];
    }

    /**
     * Delete profile photo, thumbnail and medium for a Face.
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

        if ($face->profile_photo_medium) {
            $mediumPath = self::MEDIUM_PATH . '/' . $face->profile_photo_medium;
            if ($disk->exists($mediumPath)) {
                $disk->delete($mediumPath);
                $deleted = true;
            }
        }

        // Clear the database fields
        if ($face->profile_photo || $face->profile_photo_thumbnail || $face->profile_photo_medium) {
            $face->update([
                'profile_photo' => null,
                'profile_photo_thumbnail' => null,
                'profile_photo_medium' => null,
            ]);
        }

        return $deleted;
    }

    /**
     * Generate a 150×150 JPEG thumbnail.
     */
    private function generateThumbnail(UploadedFile $photo, string $filename): string
    {
        $image = Image::read($photo->getRealPath());
        $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

        $encoded = $image->toJpeg(self::QUALITY);
        Storage::disk('public')->put(self::THUMBNAIL_PATH . '/' . $filename, $encoded->toString());

        return $filename;
    }

    /**
     * Generate an 800px-wide WebP medium image (preserving aspect ratio).
     */
    private function generateMedium(UploadedFile $photo, string $mediumFilename): void
    {
        $image = Image::read($photo->getRealPath());
        $image->scaleDown(width: self::MEDIUM_WIDTH);

        $encoded = $image->toWebp(self::QUALITY);
        Storage::disk('public')->put(self::MEDIUM_PATH . '/' . $mediumFilename, $encoded->toString());
    }
}
