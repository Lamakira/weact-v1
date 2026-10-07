<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Suppression SANS PERTE des métadonnées d'une image, au niveau des octets (aucun
 * décodage : mémoire constante, quelle que soit la taille de l'image).
 *
 * JPEG : ALLOWLIST de segments. On garde APP0 (JFIF/JFXX), APP2 SEULEMENT s'il
 * porte un profil ICC (`ICC_PROFILE\0`, multi-chunks dans l'ordre : les couleurs
 * sont préservées), APP14 (Adobe) et les segments de décodage (DQT, DHT, SOF, DRI,
 * DAC, DNL, SOS). Tout le reste est retiré : APP1 (Exif ET XMP), APP2 non-ICC
 * (dont MPF, qui pointe vers des images secondaires), APP3, APP11 (JUMBF/C2PA),
 * APP12, APP13, APP15, COM… Les données entropiques sont parcourues (octets
 * `FF00`, marqueurs RSTn, scans multiples) pour trouver l'EOI de l'image
 * PRINCIPALE : tout octet APRÈS cet EOI (images MPF secondaires avec leur propre
 * Exif, trailers Samsung SEFT, vidéo des Motion Photos…) est supprimé et compte
 * comme « sale ». Parseur tolérant façon libjpeg : les octets parasites entre
 * segments d'en-tête sont ignorés (bornés) ; seul un fichier sans SOS valide est
 * refusé. Si l'EXIF d'origine portait une Orientation ≠ 1, on insère un APP1
 * Exif MINIMAL ne contenant QUE ce tag (les navigateurs le respectent) : ni GPS,
 * ni appareil, ni dates ne survivent.
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

    private const CHUNK = 65536;

    /** Octets parasites tolérés avant le SOS (libjpeg-like), au-delà : refus. */
    private const MAX_STRAY_BYTES = 65536;

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
        $buf = (string) fread($in, self::CHUNK);
        $p = 0;

        if (! str_starts_with($buf, "\xFF\xD8")) {
            throw new \RuntimeException('Corrupt JPEG: no SOI.');
        }
        self::write($out, "\xFF\xD8");
        $p = 2;

        // Garantit $need octets disponibles à partir du début du tampon (compacte puis lit).
        $fill = function (int $need) use (&$buf, &$p, $in): bool {
            while (strlen($buf) < $need) {
                if ($p > 0) {
                    $buf = substr($buf, $p);
                    $need -= $p;
                    $p = 0;
                }
                $more = fread($in, self::CHUNK);
                if ($more === false || $more === '') {
                    return false;
                }
                $buf .= $more;
            }

            return true;
        };

        $dirty = false;
        $orientationDone = false;
        $sawSos = false;
        $stray = 0;

        while (true) {
            if (! $fill($p + 1)) {
                return self::jpegEnd($sawSos, $dirty);
            }

            $ff = strpos($buf, "\xFF", $p);
            if ($ff === false) {
                // Aucun marqueur dans le tampon : données entropiques, ou octets parasites.
                $chunk = substr($buf, $p);
                $buf = '';
                $p = 0;
                if ($sawSos) {
                    self::write($out, $chunk);
                } else {
                    $stray += strlen($chunk);
                    if (! self::strayOk($stray, $out, $dirty)) {
                        return true;
                    }
                }

                continue;
            }

            if ($ff > $p) {
                $chunk = substr($buf, $p, $ff - $p);
                if ($sawSos) {
                    self::write($out, $chunk);
                } else {
                    $stray += strlen($chunk);
                    if (! self::strayOk($stray, $out, $dirty)) {
                        return true;
                    }
                }
                $p = $ff;
            }

            if (! $fill($p + 2)) {
                // « FF » isolé en fin de fichier
                if ($sawSos) {
                    self::write($out, substr($buf, $p));
                }

                return self::jpegEnd($sawSos, $dirty);
            }

            $code = ord($buf[$p + 1]);

            if ($code === 0xFF) { // octet de remplissage avant un marqueur
                $p += 1;

                continue;
            }

            if ($sawSos && ($code === 0x00 || ($code >= 0xD0 && $code <= 0xD7))) { // FF00 / RSTn : données entropiques
                self::write($out, substr($buf, $p, 2));
                $p += 2;

                continue;
            }

            if ($code === 0x00 || $code === 0x01 || ($code >= 0xD0 && $code <= 0xD8)) { // sans longueur, hors scan : parasite
                $stray += 2;
                if (! self::strayOk($stray, $out, $dirty)) {
                    return true;
                }
                $p += 2;

                continue;
            }

            if ($code === 0xD9) { // EOI de l'image principale
                if (! $sawSos) {
                    throw new \RuntimeException('Corrupt JPEG: EOI before any SOS.');
                }
                self::write($out, "\xFF\xD9");
                $p += 2;

                // Tout ce qui suit l'EOI est retiré (et signalé).
                if ($fill($p + 1)) {
                    if ($out === null) {
                        return true;
                    }
                    $dirty = true;
                }

                return $dirty;
            }

            if (! $fill($p + 4)) {
                return self::jpegEnd($sawSos, $dirty); // segment tronqué
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', substr($buf, $p + 2, 2));
            $length = $unpacked[1];
            if ($length < 2) { // longueur invalide : marqueur parasite
                $stray += 2;
                if (! self::strayOk($stray, $out, $dirty)) {
                    return true;
                }
                $p += 2;

                continue;
            }
            if (! $fill($p + 2 + $length)) {
                return self::jpegEnd($sawSos, $dirty); // segment tronqué
            }

            $raw = substr($buf, $p, 2 + $length);
            $payload = substr($raw, 4);
            $p += 2 + $length;

            $replacement = null;
            $keep = self::keepSegment($code, $payload);

            if ($code === 0xE1 && ! $sawSos && ! $orientationDone && str_starts_with($payload, self::EXIF_HEADER)) {
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

            if ($code === 0xDA) {
                $sawSos = true;
            }

            if (! $keep) {
                if ($out === null) {
                    return true;
                }
                $dirty = true;
            }

            if ($keep) {
                self::write($out, $raw);
            } elseif ($replacement !== null) {
                self::write($out, "\xFF\xE1".pack('n', strlen($replacement) + 2).$replacement);
            }
        }
    }

    private static function jpegEnd(bool $sawSos, bool $dirty): bool
    {
        if (! $sawSos) {
            throw new \RuntimeException('Corrupt JPEG: no valid SOS found.');
        }

        return $dirty; // fichier tronqué après le SOS : on s'arrête là
    }

    /**
     * Comptabilise des octets parasites avant le SOS (supprimés, donc « sale »).
     *
     * @param  resource|null  $out
     * @return bool Faux si on doit s'arrêter (détection : déjà sale)
     *
     * @throws \RuntimeException Trop d'octets parasites : pas un JPEG exploitable
     */
    private static function strayOk(int $stray, $out, bool &$dirty): bool
    {
        if ($stray > self::MAX_STRAY_BYTES) {
            throw new \RuntimeException('Corrupt JPEG: no valid SOS found.');
        }
        if ($out === null) {
            return false;
        }
        $dirty = true;

        return true;
    }

    /**
     * Allowlist des segments JPEG conservés.
     */
    private static function keepSegment(int $code, string $payload): bool
    {
        return match (true) {
            $code === 0xE0 => str_starts_with($payload, "JFIF\0") || str_starts_with($payload, "JFXX\0"),
            $code === 0xE2 => str_starts_with($payload, "ICC_PROFILE\0"),
            $code === 0xEE => str_starts_with($payload, 'Adobe'),
            $code === 0xDB, $code === 0xDD, $code === 0xDC, $code === 0xDA => true, // DQT, DRI, DNL, SOS
            $code >= 0xC0 && $code <= 0xCF => $code !== 0xC8, // SOF*, DHT (C4), DAC (CC) ; C8 réservé
            default => false,
        };
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
                $extra = fread($in, 1);
                if ($extra !== false && $extra !== '') { // octets après IEND : retirés (le flux s'arrête ici)
                    if ($out === null) {
                        return true;
                    }
                    $dirty = true;
                }

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
