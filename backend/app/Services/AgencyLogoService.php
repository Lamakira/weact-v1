<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateAgencyLogoThumbnail;
use App\Models\Producer;
use App\Support\UploadedMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AgencyLogoService
{
    private const STORAGE_PATH = 'logos/agencies';

    private const THUMBNAIL_PATH = 'logos/agencies/thumbnails';

    /**
     * Upload a logo for an Agency Producer ; la vignette est générée par un job de queue.
     *
     * @return array{logo: string, thumbnail: string|null}
     *
     * @throws InvalidArgumentException If producer is not an Agency
     */
    public function uploadLogo(Producer $producer, UploadedFile $logo): array
    {
        // Verify producer is Agency type
        if (! $producer->isAgency()) {
            throw new InvalidArgumentException('Only Agency producers can upload logos.');
        }

        // Delete old logos if they exist
        $this->deleteLogo($producer);

        // Original ré-encodé (EXIF supprimé), extension dérivée du contenu
        $filename = UploadedMedia::storeImage('public', self::STORAGE_PATH, $logo, 'logo');

        // Aucun décodage dans la requête (memory_limit FPM) : la vignette est générée par
        // un job de queue ; la colonne reste null (URL de vignette null) en attendant.
        $producer->update([
            'agency_logo' => $filename,
            'agency_logo_thumbnail' => null,
        ]);

        dispatch(new GenerateAgencyLogoThumbnail($producer->id, $filename));

        return [
            'logo' => $filename,
            'thumbnail' => null,
        ];
    }

    /**
     * Delete logo and thumbnail for a Producer.
     */
    public function deleteLogo(Producer $producer): bool
    {
        $deleted = false;
        $disk = Storage::disk('public');

        if ($producer->agency_logo) {
            $logoPath = self::STORAGE_PATH.'/'.$producer->agency_logo;
            if ($disk->exists($logoPath)) {
                $disk->delete($logoPath);
                $deleted = true;
            }
        }

        if ($producer->agency_logo_thumbnail) {
            $thumbnailPath = self::THUMBNAIL_PATH.'/'.$producer->agency_logo_thumbnail;
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
}
