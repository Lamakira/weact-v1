<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * Détection + nettoyage RÉTROACTIF des métadonnées (EXIF/GPS, atomes de
 * localisation) des médias déjà stockés. Chaque écriture passe par un fichier
 * temporaire puis rename atomique : l'original n'est remplacé qu'après un
 * ré-encodage/remux réussi, et garde son chemin (les rows DB le référencent).
 */
final class MediaMetadataCleaner
{
    /** Tags de conteneur structurels qui subsistent après un remux propre. */
    private const STRUCTURAL_VIDEO_TAGS = ['major_brand', 'minor_version', 'compatible_brands', 'encoder'];

    /**
     * JPEG : segments APP1 (EXIF/XMP), APP13 (IPTC) avant le début du scan.
     * PNG : chunks eXIf / tEXt / iTXt / zTXt.
     *
     * @throws \RuntimeException Fichier illisible ou ni JPEG ni PNG
     */
    public static function imageHasMetadata(string $fullPath): bool
    {
        $handle = @fopen($fullPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unreadable image.');
        }

        try {
            $magic = (string) fread($handle, 8);

            if (str_starts_with($magic, "\xFF\xD8")) {
                fseek($handle, 2);

                return self::jpegHasMetadata($handle);
            }

            if ($magic === "\x89PNG\r\n\x1a\n") {
                return self::pngHasMetadata($handle);
            }
        } finally {
            fclose($handle);
        }

        throw new \RuntimeException('Not a JPEG/PNG file.');
    }

    /**
     * @param  resource  $handle
     */
    private static function jpegHasMetadata($handle): bool
    {
        while (! feof($handle)) {
            $head = fread($handle, 4);
            if ($head === false || strlen($head) < 4 || $head[0] !== "\xFF") {
                return false;
            }

            $marker = ord($head[1]);
            if ($marker === 0xDA || $marker === 0xD9) { // SOS / EOI : plus de segments d'en-tête
                return false;
            }
            if ($marker === 0xE1 || $marker === 0xED) {
                return true;
            }

            /** @var array{1: int} $len */
            $len = unpack('n', substr($head, 2, 2));
            fseek($handle, $len[1] - 2, SEEK_CUR);
        }

        return false;
    }

    /**
     * @param  resource  $handle
     */
    private static function pngHasMetadata($handle): bool
    {
        while (! feof($handle)) {
            $head = fread($handle, 8);
            if ($head === false || strlen($head) < 8) {
                return false;
            }

            /** @var array{1: int} $len */
            $len = unpack('N', substr($head, 0, 4));
            $type = substr($head, 4, 4);

            if (in_array($type, ['eXIf', 'tEXt', 'iTXt', 'zTXt'], true)) {
                return true;
            }
            if ($type === 'IDAT' || $type === 'IEND') {
                return false;
            }

            fseek($handle, $len[1] + 4, SEEK_CUR); // données + CRC
        }

        return false;
    }

    /**
     * Ré-encode l'image en place (orientation appliquée aux pixels, métadonnées
     * supprimées) : temp dans le même dossier, puis rename atomique.
     *
     * @throws \Throwable Si le décodage/l'écriture échoue (l'original reste intact)
     */
    public static function cleanImage(string $fullPath): void
    {
        $png = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'png';
        $temp = $fullPath.'.cleaning.tmp';

        try {
            $bytes = UploadedMedia::reencode($fullPath, $png);

            if (file_put_contents($temp, $bytes) === false) {
                throw new \RuntimeException('Cannot write temporary file.');
            }

            $perms = @fileperms($fullPath);
            if ($perms !== false) {
                @chmod($temp, $perms & 0777);
            }

            if (! rename($temp, $fullPath)) {
                throw new \RuntimeException('Cannot replace original.');
            }
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e;
        }
    }

    /**
     * Vrai si le conteneur porte un tag autre que les tags structurels
     * (localisation, modèle d'appareil, dates…).
     *
     * @throws \RuntimeException Si ffprobe échoue
     */
    public static function videoHasMetadata(string $fullPath): bool
    {
        $result = Process::timeout(60)->run([
            (string) config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
            '-v', 'error',
            '-show_entries', 'format_tags',
            '-of', 'json',
            $fullPath,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('ffprobe failed: '.trim($result->errorOutput()));
        }

        $data = json_decode($result->output(), true);
        $tags = is_array($data) && isset($data['format']['tags']) && is_array($data['format']['tags'])
            ? array_keys($data['format']['tags'])
            : [];

        foreach ($tags as $tag) {
            if (! in_array(strtolower((string) $tag), self::STRUCTURAL_VIDEO_TAGS, true)) {
                return true;
            }
        }

        return false;
    }
}
