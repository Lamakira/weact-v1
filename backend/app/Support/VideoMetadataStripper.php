<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Supprime les métadonnées d'une vidéo par un remux ffmpeg SANS ré-encodage
 * (`-c copy`) : coût quasi nul, même sur 200 Mo. Réécrit le fichier en place
 * (temp + rename).
 *
 * - `-map 0:v -map 0:a?` : on ne garde que les flux vidéo et audio. Les pistes de
 *   données (mebx iPhone, mett Pixel, tmcd), sous-titres (mov_text DJI) et
 *   télémétrie (gpmd GoPro) sont écartées : elles font échouer le `-c copy` sur
 *   les vrais fichiers de téléphone ET peuvent embarquer le GPS.
 * - `-map_metadata -1` : tags globaux (location, com.apple.quicktime.*…).
 * - La rotation est une side data « display matrix » du flux vidéo, conservée par
 *   `-c copy` (ffmpeg 6.1) : on ne touche PAS aux métadonnées de flux.
 */
final class VideoMetadataStripper
{
    public const FORMATS = ['mp4', 'mov', 'avi'];

    /**
     * Conteneur détecté par octets magiques (jamais par l'extension).
     *
     * @return 'mp4'|'mov'|'avi'|null
     */
    public static function format(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $head = (string) fread($handle, 12);
        fclose($handle);

        if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
            return substr($head, 8, 4) === 'qt  ' ? 'mov' : 'mp4';
        }

        if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'AVI ') {
            return 'avi';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function command(string $input, string $output, string $format = 'mp4'): array
    {
        return [
            (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
            '-y', '-v', 'error',
            '-i', $input,
            '-map', '0:v', '-map', '0:a?',
            '-map_metadata', '-1',
            '-map_chapters', '-1',
            '-c', 'copy',
            '-f', $format,
            $output,
        ];
    }

    /**
     * Variante tolérante pour les NOUVEAUX uploads : un remux en échec ne fait pas
     * échouer l'upload. Le fichier original reste intact (strip() ne le remplace
     * qu'après un remux réussi) et un warning est loggé ; `media:strip-metadata`
     * le rattrapera. À appeler HORS de toute transaction / lock DB.
     */
    public static function stripOrLog(string $path): void
    {
        try {
            self::strip($path);
        } catch (\Throwable $e) {
            Log::warning('video metadata strip failed — original kept, to be caught by media:strip-metadata', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @throws \RuntimeException Si le remux échoue (le fichier original n'est alors pas modifié)
     */
    public static function strip(string $path): void
    {
        $format = self::format($path);
        if ($format === null) {
            throw new \RuntimeException('Video metadata stripping failed: unrecognised container.');
        }

        $temp = $path.'.stripped.'.$format;

        try {
            $result = Process::timeout(120)->run(self::command($path, $temp, $format));

            if (! $result->successful()) {
                throw new \RuntimeException('Video metadata stripping failed: '.trim($result->errorOutput()));
            }

            if (! rename($temp, $path)) {
                throw new \RuntimeException('Video metadata stripping failed: cannot replace original.');
            }
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e;
        }
    }
}
