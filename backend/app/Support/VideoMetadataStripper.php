<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Supprime les métadonnées de conteneur d'une vidéo (atomes de localisation GPS,
 * modèle d'appareil…) par un remux ffmpeg SANS ré-encodage (`-c copy`) : coût
 * quasi nul, même sur 200 Mo. Réécrit le fichier en place (temp + rename).
 */
final class VideoMetadataStripper
{
    /**
     * @return list<string>
     */
    public static function command(string $input, string $output): array
    {
        return [
            (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
            '-y', '-v', 'error',
            '-i', $input,
            '-map', '0',
            '-map_metadata', '-1',
            '-map_metadata:s', '-1',
            '-map_chapters', '-1',
            '-c', 'copy',
            $output,
        ];
    }

    /**
     * Variante tolérante pour les NOUVEAUX uploads : un remux en échec ne fait pas
     * échouer l'upload. Le fichier original reste intact (strip() ne le remplace
     * qu'après un remux réussi) et un warning est loggé ; `media:strip-metadata`
     * le rattrapera.
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
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $temp = $path.'.stripped.'.$extension;

        try {
            $result = Process::timeout(300)->run(self::command($path, $temp));

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
