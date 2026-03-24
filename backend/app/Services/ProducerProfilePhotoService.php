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
    private const MEDIUM_PATH = 'avatars/producers/medium';
    private const THUMBNAIL_SIZE = 150;
    private const MEDIUM_WIDTH = 800;
    private const QUALITY = 85;

    /**
     * Upload a profile photo for a Producer and generate thumbnail + medium.
     *
     * @param Producer $producer
     * @param UploadedFile $photo
     * @return array{photo: string, thumbnail: string, medium: string}
     */
    public function uploadProfilePhoto(Producer $producer, UploadedFile $photo): array
    {
        $this->deleteProfilePhoto($producer);

        $extension = $photo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;
        $mediumFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';

        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $photo, $filename);

        $thumbnailFilename = $this->generateThumbnail($photo, $filename);
        $this->generateMedium($photo, $mediumFilename);

        $producer->update([
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
     * Delete profile photo, thumbnail and medium for a Producer.
     */
    public function deleteProfilePhoto(Producer $producer): bool
    {
        $deleted = false;
        $disk = Storage::disk('public');

        if ($producer->profile_photo) {
            $path = self::STORAGE_PATH . '/' . $producer->profile_photo;
            if ($disk->exists($path)) { $disk->delete($path); $deleted = true; }
        }

        if ($producer->profile_photo_thumbnail) {
            $path = self::THUMBNAIL_PATH . '/' . $producer->profile_photo_thumbnail;
            if ($disk->exists($path)) { $disk->delete($path); $deleted = true; }
        }

        if ($producer->profile_photo_medium) {
            $path = self::MEDIUM_PATH . '/' . $producer->profile_photo_medium;
            if ($disk->exists($path)) { $disk->delete($path); $deleted = true; }
        }

        if ($producer->profile_photo || $producer->profile_photo_thumbnail || $producer->profile_photo_medium) {
            $producer->update([
                'profile_photo' => null,
                'profile_photo_thumbnail' => null,
                'profile_photo_medium' => null,
            ]);
        }

        return $deleted;
    }

    private function generateThumbnail(UploadedFile $photo, string $filename): string
    {
        $image = Image::read($photo->getRealPath());
        $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);
        $encoded = $image->toJpeg(self::QUALITY);
        Storage::disk('public')->put(self::THUMBNAIL_PATH . '/' . $filename, $encoded->toString());

        return $filename;
    }

    private function generateMedium(UploadedFile $photo, string $mediumFilename): void
    {
        $image = Image::read($photo->getRealPath());
        $image->scaleDown(width: self::MEDIUM_WIDTH);
        $encoded = $image->toWebp(self::QUALITY);
        Storage::disk('public')->put(self::MEDIUM_PATH . '/' . $mediumFilename, $encoded->toString());
    }
}
