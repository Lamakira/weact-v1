<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Suppression SANS PERTE des métadonnées d'une image, au niveau des octets (aucun
 * décodage : mémoire constante, quelle que soit la taille de l'image).
 *
 * JPEG : on retire APP1 (Exif ET XMP), APP13 (IPTC/Photoshop) et COM ; on garde
 * APP0 (JFIF), APP2 (profil ICC, donc les couleurs), APP14 (Adobe) et tout ce qui
 * suit le SOS, intact. Si l'EXIF d'origine portait une Orientation ≠ 1, on
 * insère un APP1 Exif MINIMAL ne contenant QUE ce tag (les navigateurs le
 * respectent) : ni GPS, ni appareil, ni dates ne survivent.
 *
 * PNG : on retire les chunks eXIf, tEXt, iTXt, zTXt ; tout le reste (iCCP, sRGB,
 * gAMA, cHRM, pHYs, IDAT…) est copié tel quel.
 *
 * Le format est détecté par les octets magiques, jamais par l'extension.
 */
final class ImageMetadataStripper
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const PNG_DROPPED_CHUNKS = ['eXIf', 'tEXt', 'iTXt', 'zTXt'];

    private const EXIF_HEADER = "Exif\0\0";

    /**
     * @return 'jpeg'|'png'|null
     */
    public static function format(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $magic = (string) fread($handle, 8);
        fclose($handle);

        if (str_starts_with($magic, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        return $magic === self::PNG_SIGNATURE ? 'png' : null;
    }

    /**
     * Vrai si le fichier porte au moins une métadonnée amovible (hors APP1
     * d'orientation minimal déjà canonique).
     *
     * @throws \RuntimeException Fichier illisible, corrompu, ni JPEG ni PNG
     */
    public static function isDirty(string $path): bool
    {
        return self::process($path, null);
    }

    /**
     * Écrit dans $output une copie sans métadonnées de $path.
     *
     * @return bool Vrai si quelque chose a été retiré/remplacé
     *
     * @throws \RuntimeException
     */
    public static function strip(string $path, string $output): bool
    {
        $out = @fopen($output, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Cannot open output file.');
        }

        try {
            return self::process($path, $out);
        } finally {
            fclose($out);
        }
    }

    /**
     * Nettoyage en place : temp dans le même dossier puis rename atomique, SEULEMENT
     * si quelque chose a été retiré. L'original n'est jamais touché en cas d'échec.
     *
     * @return bool Vrai si le fichier a été réécrit
     *
     * @throws \RuntimeException
     */
    public static function stripInPlace(string $path): bool
    {
        $temp = $path.'.stripping.tmp';

        try {
            if (! self::strip($path, $temp)) {
                @unlink($temp);

                return false;
            }

            $perms = @fileperms($path);
            if ($perms !== false) {
                @chmod($temp, $perms & 0777);
            }

            if (! rename($temp, $path)) {
                throw new \RuntimeException('Cannot replace original.');
            }

            return true;
        } catch (\Throwable $e) {
            @unlink($temp);

            throw $e;
        }
    }

    /**
     * @param  resource|null  $out  null = simple détection (s'arrête à la 1re métadonnée)
     */
    private static function process(string $path, $out): bool
    {
        $format = self::format($path);
        if ($format === null) {
            throw new \RuntimeException('Not a JPEG/PNG file.');
        }

        $in = @fopen($path, 'rb');
        if ($in === false) {
            throw new \RuntimeException('Unreadable image.');
        }

        try {
            return $format === 'jpeg' ? self::jpeg($in, $out) : self::png($in, $out);
        } finally {
            fclose($in);
        }
    }

    /**
     * @param  resource  $in
     * @param  resource|null  $out
     */
    private static function jpeg($in, $out): bool
    {
        self::write($out, (string) fread($in, 2)); // SOI

        $dirty = false;
        $orientationDone = false;

        while (true) {
            $byte = fread($in, 1);
            if ($byte === false || $byte === '') {
                return $dirty; // fin de fichier avant SOS : on s'arrête là
            }
            if ($byte !== "\xFF") {
                throw new \RuntimeException('Corrupt JPEG: marker expected.');
            }

            do { // octets de remplissage 0xFF éventuels
                $marker = fread($in, 1);
                if ($marker === false || $marker === '') {
                    throw new \RuntimeException('Corrupt JPEG: truncated marker.');
                }
            } while ($marker === "\xFF");

            $code = ord($marker);

            if ($code === 0xD9) { // EOI
                self::write($out, "\xFF\xD9");

                return $dirty;
            }
            if ($code === 0x01 || ($code >= 0xD0 && $code <= 0xD8)) { // marqueurs sans longueur
                self::write($out, "\xFF".$marker);

                continue;
            }
            if ($code === 0xDA) { // SOS : tout le reste est copié tel quel
                if ($out !== null) {
                    self::write($out, "\xFF".$marker);
                    stream_copy_to_stream($in, $out);
                }

                return $dirty;
            }

            $lengthBytes = (string) fread($in, 2);
            if (strlen($lengthBytes) < 2) {
                throw new \RuntimeException('Corrupt JPEG: truncated segment.');
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', $lengthBytes);
            $length = $unpacked[1];
            if ($length < 2) {
                throw new \RuntimeException('Corrupt JPEG: bad segment length.');
            }
            $payload = $length > 2 ? (string) fread($in, $length - 2) : '';
            if (strlen($payload) !== $length - 2) {
                throw new \RuntimeException('Corrupt JPEG: truncated segment.');
            }

            $replacement = null;
            $keep = true;

            if ($code === 0xED || $code === 0xFE) { // IPTC / commentaire
                $keep = false;
            } elseif ($code === 0xE1) { // APP1 : Exif ou XMP
                $keep = false;

                if (! $orientationDone && str_starts_with($payload, self::EXIF_HEADER)) {
                    $orientation = self::exifOrientation(substr($payload, 6));

                    if ($orientation !== null && $orientation > 1) {
                        $orientationDone = true;
                        $minimal = self::minimalExifPayload($orientation);

                        if ($payload === $minimal) {
                            $keep = true; // déjà canonique : idempotent
                        } else {
                            $replacement = $minimal;
                        }
                    }
                }
            }

            if (! $keep) {
                if ($out === null) {
                    return true;
                }
                $dirty = true;
            }

            if ($keep) {
                self::write($out, "\xFF".$marker.$lengthBytes.$payload);
            } elseif ($replacement !== null) {
                self::write($out, "\xFF\xE1".pack('n', strlen($replacement) + 2).$replacement);
            }
        }
    }

    /**
     * @param  resource  $in
     * @param  resource|null  $out
     */
    private static function png($in, $out): bool
    {
        self::write($out, (string) fread($in, 8));
        $dirty = false;

        while (true) {
            $header = (string) fread($in, 8);
            if (strlen($header) < 8) {
                return $dirty;
            }

            /** @var array{1: int} $unpacked */
            $unpacked = unpack('N', substr($header, 0, 4));
            $length = $unpacked[1];
            $type = substr($header, 4, 4);
            $rest = $length + 4; // données + CRC

            if (in_array($type, self::PNG_DROPPED_CHUNKS, true)) {
                if ($out === null) {
                    return true;
                }
                $dirty = true;
                fseek($in, $rest, SEEK_CUR);

                continue;
            }

            if ($out !== null) {
                self::write($out, $header);
                if (stream_copy_to_stream($in, $out, $rest) !== $rest) {
                    throw new \RuntimeException('Corrupt PNG: truncated chunk.');
                }
            } else {
                fseek($in, $rest, SEEK_CUR);
            }

            if ($type === 'IEND') {
                return $dirty;
            }
        }
    }

    /**
     * Payload d'un APP1 Exif ne contenant QUE le tag Orientation (TIFF little-endian).
     */
    private static function minimalExifPayload(int $orientation): string
    {
        return self::EXIF_HEADER
            ."II*\0".pack('V', 8)            // en-tête TIFF, IFD0 à l'offset 8
            .pack('v', 1)                    // 1 entrée
            .pack('vvV', 0x0112, 3, 1)       // Orientation, SHORT, count 1
            .pack('v', $orientation)."\0\0"  // valeur + remplissage
            .pack('V', 0);                   // pas d'IFD suivant
    }

    private static function exifOrientation(string $tiff): ?int
    {
        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') {
            return null;
        }
        $little = $order === 'II';

        $ifd = self::readInt($tiff, 4, 4, $little);
        $count = $ifd === null ? null : self::readInt($tiff, $ifd, 2, $little);
        if ($ifd === null || $count === null) {
            return null;
        }

        for ($i = 0; $i < min($count, 256); $i++) {
            $entry = $ifd + 2 + 12 * $i;

            if (self::readInt($tiff, $entry, 2, $little) !== 0x0112) {
                continue;
            }
            if (self::readInt($tiff, $entry + 2, 2, $little) !== 3) {
                return null;
            }
            $value = self::readInt($tiff, $entry + 8, 2, $little);

            return $value !== null && $value >= 1 && $value <= 8 ? $value : null;
        }

        return null;
    }

    private static function readInt(string $data, int $offset, int $size, bool $little): ?int
    {
        if ($offset < 0 || $offset + $size > strlen($data)) {
            return null;
        }

        $format = $size === 2 ? ($little ? 'v' : 'n') : ($little ? 'V' : 'N');
        /** @var array{1: int} $unpacked */
        $unpacked = unpack($format, substr($data, $offset, $size));

        return $unpacked[1];
    }

    /**
     * @param  resource|null  $out
     */
    private static function write($out, string $bytes): void
    {
        if ($out === null || $bytes === '') {
            return;
        }

        if (fwrite($out, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException('Write failed.');
        }
    }
}
