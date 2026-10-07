<?php

declare(strict_types=1);

namespace App\Support;

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
     * @throws \RuntimeException Si le remux échoue (le fichier original n'est alors pas modifié)
     */
    public static function strip(string $path): void
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $temp = $path.'.stripped.'.$extension;

        $result = Process::timeout(300)->run(self::command($path, $temp));

        if (! $result->successful()) {
            @unlink($temp);

            throw new \RuntimeException('Video metadata stripping failed: '.trim($result->errorOutput()));
        }

        if (! rename($temp, $path)) {
            @unlink($temp);

            throw new \RuntimeException('Video metadata stripping failed: cannot replace original.');
        }
    }
}
