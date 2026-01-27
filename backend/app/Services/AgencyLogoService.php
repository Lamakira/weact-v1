<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Producer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;

class AgencyLogoService
{
    private const STORAGE_PATH = 'logos/agencies';
    private const THUMBNAIL_PATH = 'logos/agencies/thumbnails';
    private const THUMBNAIL_SIZE = 150;
    private const THUMBNAIL_QUALITY = 85;

    /**
     * Upload a logo for an Agency Producer and generate thumbnail.
     *
     * @param Producer $producer
     * @param UploadedFile $logo
     * @return array{logo: string, thumbnail: string}
     * @throws InvalidArgumentException If producer is not an Agency
     */
    public function uploadLogo(Producer $producer, UploadedFile $logo): array
    {
        // Verify producer is Agency type
        if (!$producer->isAgency()) {
            throw new InvalidArgumentException('Only Agency producers can upload logos.');
        }

        // Delete old logos if they exist
        $this->deleteLogo($producer);

        // Generate unique filename with UUID
        $extension = $logo->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString() . '.' . $extension;

        // Store original logo using the public disk
        Storage::disk('public')->putFileAs(self::STORAGE_PATH, $logo, $filename);

        // Generate and save thumbnail from the uploaded file directly
        $thumbnailFilename = $this->generateThumbnail($logo, $filename);

        // Update Producer model
        $producer->update([
            'agency_logo' => $filename,
            'agency_logo_thumbnail' => $thumbnailFilename,
        ]);

        return [
            'logo' => $filename,
            'thumbnail' => $thumbnailFilename,
        ];
    }

    /**
     * Delete logo and thumbnail for a Producer.
     *
     * @param Producer $producer
     * @return bool
     */
    public function deleteLogo(Producer $producer): bool
    {
        $deleted = false;
        $disk = Storage::disk('public');

        if ($producer->agency_logo) {
            $logoPath = self::STORAGE_PATH . '/' . $producer->agency_logo;
            if ($disk->exists($logoPath)) {
                $disk->delete($logoPath);
                $deleted = true;
            }
        }

        if ($producer->agency_logo_thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH . '/' . $producer->agency_logo_thumbnail;
            if ($disk->exists($thumbnailPath)) {
                $disk->delete($thumbnailPath);
                $deleted = true;
            }
        }

        // Clear the database fields
        if ($producer->agency_logo || $producer->agency_logo_thumbnail) {
            $producer->update([
                'agency_logo' => null,
                'agency_logo_thumbnail' => null,
            ]);
        }

        return $deleted;
    }

    /**
     * Generate a thumbnail from the uploaded logo.
     *
     * @param UploadedFile $logo
     * @param string $filename
     * @return string The thumbnail filename (always .jpg extension)
     */
    private function generateThumbnail(UploadedFile $logo, string $filename): string
    {
        // Read the original image from the uploaded file's temp path
        $image = Image::read($logo->getRealPath());
        $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

        // Convert to JPEG and get the encoded data
        $encoded = $image->toJpeg(self::THUMBNAIL_QUALITY);

        // Thumbnail filename always uses .jpg extension since we convert to JPEG
        $thumbnailFilename = Str::beforeLast($filename, '.') . '.jpg';

        // Store thumbnail using public disk (works with fake storage in tests)
        Storage::disk('public')->put(self::THUMBNAIL_PATH . '/' . $thumbnailFilename, $encoded->toString());

        return $thumbnailFilename;
    }
}
