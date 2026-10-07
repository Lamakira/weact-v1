<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Supprime les métadonnées d'une vidéo par un remux ffmpeg SANS ré-encodage vidéo
 * (`-c copy`) : coût quasi nul, même sur 200 Mo. Réécrit le fichier en place
 * (temp unique + rename).
 *
 * - `-map 0:v -map 0:a?` : on ne garde que les flux vidéo et audio. Les pistes de
 *   données (mebx iPhone, mett Pixel, tmcd), sous-titres (mov_text DJI) et
 *   télémétrie (gpmd GoPro) sont écartées : elles font échouer le `-c copy` sur
 *   les vrais fichiers de téléphone ET peuvent embarquer le GPS.
 * - `-map_metadata -1` (nu) efface les métadonnées GLOBALES, de FLUX et de
 *   CHAPITRES. La rotation n'est pas une métadonnée : c'est une side data
 *   « display matrix » du flux vidéo, conservée par `-c copy` (ffmpeg 6.1).
 * - Repli : si le `-c copy` échoue (codec audio inconnu, ex. audio spatial `apac`
 *   de l'iPhone 16), on retente UNE fois avec `-c:v copy -c:a aac -b:a 160k`
 *   (audio ré-encodé, vidéo intacte). Si cela échoue aussi, l'original est conservé.
 */
final class VideoMetadataStripper
{
    public const FORMATS = ['mp4', 'mov', 'avi'];

    private const TIMEOUT_SECONDS = 120;

    /** Atomes de tête ignorés lors de la détection du conteneur ISO-BMFF. */
    private const SKIPPABLE_ATOMS = ['wide', 'free', 'skip', 'junk', 'pnot'];

    /**
     * Conteneur détecté par octets magiques (jamais par l'extension). Pour
     * l'ISO-BMFF, parcourt les premiers atomes de premier niveau (`wide`, `free`,
     * `skip`… peuvent précéder `ftyp`/`moov`/`mdat`).
     *
     * @return 'mp4'|'mov'|'avi'|null
     */
    public static function format(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $head = (string) fread($handle, 12);
            if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'AVI ') {
                return 'avi';
            }

            rewind($handle);

            for ($i = 0; $i < 8; $i++) {
                $atom = (string) fread($handle, 8);
                if (strlen($atom) < 8) {
                    return null;
                }

                /** @var array{1: int} $unpacked */
                $unpacked = unpack('N', substr($atom, 0, 4));
                $size = $unpacked[1];
                $type = substr($atom, 4, 4);

                if ($type === 'ftyp') {
                    return fread($handle, 4) === 'qt  ' ? 'mov' : 'mp4';
                }
                if ($type === 'moov' || $type === 'mdat') {
                    return 'mov'; // style QuickTime sans ftyp
                }
                if (! in_array($type, self::SKIPPABLE_ATOMS, true) || $size === 0) {
                    return null;
                }

                if ($size === 1) { // taille 64 bits
                    $large = (string) fread($handle, 8);
                    if (strlen($large) < 8) {
                        return null;
                    }
                    /** @var array{1: int, 2: int} $parts */
                    $parts = unpack('N2', $large);
                    $skip = ($parts[1] << 32 | $parts[2]) - 16;
                } else {
                    $skip = $size - 8;
                }

                if ($skip < 0 || fseek($handle, $skip, SEEK_CUR) !== 0) {
                    return null;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * @param  bool  $reencodeAudio  Repli : audio ré-encodé en AAC, vidéo toujours copiée
     * @return list<string>
     */
    public static function command(string $input, string $output, string $format = 'mp4', bool $reencodeAudio = false): array
    {
        return [
            (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg'),
            '-y', '-v', 'error',
            '-i', $input,
            '-map', '0:v', '-map', '0:a?',
            '-map_metadata', '-1',
            '-map_chapters', '-1',
            ...($reencodeAudio ? ['-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k'] : ['-c', 'copy']),
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

        $before = self::fingerprint($path);
        if ($before === null) {
            throw new \RuntimeException('Video metadata stripping failed: original not found.');
        }

        // Nom unique : un upload en cours et la commande de rétrofit ne se marchent pas dessus.
        $temp = $path.'.stripped.'.bin2hex(random_bytes(6)).'.'.$format;

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->run(self::command($path, $temp, $format));

            if (! $result->successful()) {
                @unlink($temp);
                // Repli : codec audio inconnu avec -c copy → audio ré-encodé, vidéo intacte.
                $result = Process::timeout(self::TIMEOUT_SECONDS)->run(self::command($path, $temp, $format, true));
            }

            if (! $result->successful()) {
                throw new \RuntimeException('Video metadata stripping failed: '.trim($result->errorOutput()));
            }

            // L'original a pu être supprimé / remplacé pendant le remux : ne rien ressusciter.
            if (self::fingerprint($path) !== $before) {
                throw new \RuntimeException('Video metadata stripping aborted: original changed or removed meanwhile.');
            }

            $perms = @fileperms($path);
            if ($perms !== false) {
                @chmod($temp, $perms & 0777);
            }

            if (! rename($temp, $path)) {
                throw new \RuntimeException('Video metadata stripping failed: cannot replace original.');
            }
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e;
        }
    }

    /**
     * @return array{int, int, int}|null inode, taille, mtime
     */
    private static function fingerprint(string $path): ?array
    {
        clearstatcache(true, $path);
        $stat = @stat($path);

        return $stat === false ? null : [$stat['ino'], $stat['size'], $stat['mtime']];
    }
}
