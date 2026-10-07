<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Producer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Génère la vignette 150x150 du logo d'une agence dans le worker de queue (le
 * décodage GD ne doit jamais avoir lieu dans la requête : un petit PNG de 8000 px
 * dépasse memory_limit=128M en FPM). La cible est portée par (id, nom de fichier du
 * logo) : si le logo a été remplacé/supprimé entre-temps, le job est un no-op loggé
 * (convention anti queue-poison de GenerateImageVariants).
 */
class GenerateAgencyLogoThumbnail implements ShouldQueue
{
    use Queueable;

    public const LOGO_PATH = 'logos/agencies';

    public const THUMBNAIL_PATH = 'logos/agencies/thumbnails';

    private const THUMBNAIL_SIZE = 150;

    private const THUMBNAIL_QUALITY = 85;

    public function __construct(
        public readonly int $producerId,
        public readonly string $logo,
    ) {
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $producer = Producer::find($this->producerId);
        $disk = Storage::disk('public');

        if (! $producer instanceof Producer
            || $producer->agency_logo !== $this->logo
            || ! $disk->exists(self::LOGO_PATH.'/'.$this->logo)) {
            Log::info('GenerateAgencyLogoThumbnail: logo remplacé, supprimé ou absent, job ignoré', [
                'producer_id' => $this->producerId,
                'logo' => $this->logo,
            ]);

            return;
        }

        $thumbnailFilename = Str::beforeLast($this->logo, '.').'.jpg';
        $thumbnailPath = self::THUMBNAIL_PATH.'/'.$thumbnailFilename;

        try {
            $image = Image::read((string) $disk->get(self::LOGO_PATH.'/'.$this->logo));
            $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

            if ($disk->put($thumbnailPath, $image->toJpeg(self::THUMBNAIL_QUALITY)->toString()) === false) {
                throw new \RuntimeException("Failed to write logo thumbnail [{$thumbnailPath}]");
            }
        } catch (\Throwable $e) {
            // Source illisible / encodeur en échec : déterministe, un retry n'y changerait rien.
            Log::warning('GenerateAgencyLogoThumbnail: échec de génération, job abandonné', [
                'producer_id' => $this->producerId,
                'logo' => $this->logo,
                'exception' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return;
        }

        // Garde optimiste : ne réclame la colonne que si le logo est toujours le même.
        $claimed = Producer::query()
            ->whereKey($this->producerId)
            ->where('agency_logo', $this->logo)
            ->update(['agency_logo_thumbnail' => $thumbnailFilename]);

        if ($claimed === 0) {
            $disk->delete($thumbnailPath); // logo changé pendant la génération : pas d'orphelin
        }
    }
}
