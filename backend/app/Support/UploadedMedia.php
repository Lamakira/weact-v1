<?php

declare(strict_types=1);

namespace App\Support;

use App\Rules\MaxImageDimensions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Durcissement des uploads : l'extension stockée est dérivée du CONTENU détecté,
 * jamais du nom client, via une allowlist explicite par type de média. Un JPEG
 * valide envoyé sous `x.html` n'est donc jamais servi en text/html depuis le
 * domaine de l'API.
 *
 * Les images sont nettoyées SANS PERTE au niveau des octets (ImageMetadataStripper :
 * EXIF/GPS/XMP/IPTC retirés, ICC conservé, Orientation minimale conservée) :
 * aucun décodage dans la requête, mémoire constante. Le décodage reste dans le
 * job de génération des variantes (worker de queue).
 */
final class UploadedMedia
{
    /** Allowlist vidéos : mime détecté => extension stockée. */
    public const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/avi' => 'avi',
        'video/msvideo' => 'avi',
    ];

    /** Plafond en pixels par côté (protège le worker qui décode les variantes). */
    public const MAX_IMAGE_DIMENSION = 8000;

    /**
     * @param  string  $field  Nom du champ de la requête (clé de l'erreur de validation)
     *
     * @throws ValidationException Type de vidéo non autorisé
     */
    public static function videoExtension(UploadedFile $file, string $field): string
    {
        $mime = $file->getMimeType();

        if (! is_string($mime) || ! isset(self::VIDEO_EXTENSIONS[strtolower($mime)])) {
            throw self::invalid($field);
        }

        return self::VIDEO_EXTENSIONS[strtolower($mime)];
    }

    /**
     * Règle de validation partagée par tous les uploads d'image.
     */
    public static function maxDimensions(): MaxImageDimensions
    {
        return new MaxImageDimensions;
    }

    /**
     * Écrit l'image sur le disque, débarrassée de ses métadonnées (copie
     * octet-à-octet sans décodage), sous un nom UUID + extension issue des octets
     * magiques (jpg|png).
     *
     * @param  string  $field  Nom du champ de la requête (clé de l'erreur de validation)
     * @return string Le nom de fichier stocké
     *
     * @throws ValidationException Format non autorisé ou image corrompue
     * @throws \RuntimeException Échec d'écriture sur le disque
     */
    public static function storeImage(string $disk, string $directory, UploadedFile $file, string $field): string
    {
        $source = $file->getRealPath();
        $format = $source === false ? null : ImageMetadataStripper::format($source);
        if ($source === false || $format === null) {
            throw self::invalid($field);
        }

        $filename = Str::uuid()->toString().'.'.($format === 'png' ? 'png' : 'jpg');

        $temp = tempnam(sys_get_temp_dir(), 'img');
        if ($temp === false) {
            throw new \RuntimeException('Cannot create temporary file.');
        }

        try {
            try {
                ImageMetadataStripper::strip($source, $temp);
            } catch (\RuntimeException) {
                throw self::invalid($field);
            }

            $stream = fopen($temp, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Cannot read temporary file.');
            }

            try {
                $written = Storage::disk($disk)->put($directory.'/'.$filename, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($temp);
        }

        if ($written === false) {
            throw new \RuntimeException("Failed to store image [{$filename}] on disk [{$disk}].");
        }

        return $filename;
    }

    private static function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => ['Format de fichier non supporté.']]);
    }
}
