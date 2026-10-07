<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Durcissement des uploads : l'extension stockée est dérivée du CONTENU détecté
 * (mime sniffé), jamais du nom client, via une allowlist explicite par type de
 * média. Un JPEG valide envoyé sous `x.html` n'est donc jamais servi en
 * text/html depuis le domaine de l'API.
 *
 * Les images sont ré-encodées une fois à l'upload (GD) : les métadonnées EXIF
 * (GPS, appareil…) sont supprimées, l'orientation EXIF étant appliquée aux
 * pixels AVANT (autoOrientation à la lecture).
 */
final class UploadedMedia
{
    /** Allowlist images : mime détecté => extension stockée. */
    public const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** Allowlist vidéos : mime détecté => extension stockée. */
    public const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/avi' => 'avi',
        'video/msvideo' => 'avi',
    ];

    /** Plafond en pixels par côté (anti decompression bomb). */
    public const MAX_IMAGE_DIMENSION = 6000;

    private const JPEG_QUALITY = 85;

    public static function imageExtension(UploadedFile $file): string
    {
        return self::extensionFor($file, self::IMAGE_EXTENSIONS);
    }

    public static function videoExtension(UploadedFile $file): string
    {
        return self::extensionFor($file, self::VIDEO_EXTENSIONS);
    }

    /**
     * Règle de validation `dimensions` partagée par tous les uploads d'image.
     */
    public static function maxDimensions(): Dimensions
    {
        return Rule::dimensions()
            ->maxWidth(self::MAX_IMAGE_DIMENSION)
            ->maxHeight(self::MAX_IMAGE_DIMENSION);
    }

    /**
     * Ré-encode l'image (sans EXIF, orientation appliquée) et l'écrit sur le
     * disque sous un nom UUID + extension issue de l'allowlist.
     *
     * @return string Le nom de fichier stocké
     *
     * @throws ValidationException Type non autorisé ou image illisible
     * @throws \RuntimeException Échec d'écriture sur le disque
     */
    public static function storeImage(string $disk, string $directory, UploadedFile $file): string
    {
        $extension = self::imageExtension($file);
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $file->getRealPath();
        if ($path === false) {
            throw self::invalid();
        }

        try {
            $image = Image::read($path); // autoOrientation (config/image.php) : pixels déjà orientés
            $bytes = ($extension === 'png' ? $image->toPng() : $image->toJpeg(self::JPEG_QUALITY))->toString();
        } catch (\Throwable) {
            throw self::invalid();
        }

        if (Storage::disk($disk)->put($directory.'/'.$filename, $bytes) === false) {
            throw new \RuntimeException("Failed to store image [{$filename}] on disk [{$disk}].");
        }

        return $filename;
    }

    /**
     * @param  array<string, string>  $allowlist
     */
    private static function extensionFor(UploadedFile $file, array $allowlist): string
    {
        $mime = $file->getMimeType();

        if (! is_string($mime) || ! isset($allowlist[strtolower($mime)])) {
            throw self::invalid();
        }

        return $allowlist[strtolower($mime)];
    }

    private static function invalid(): ValidationException
    {
        return ValidationException::withMessages(['file' => ['Format de fichier non supporté.']]);
    }
}
