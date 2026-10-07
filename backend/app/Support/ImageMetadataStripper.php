<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Suppression SANS PERTE des métadonnées d'une image, au niveau des octets (aucun
 * décodage des pixels : mémoire constante, quelle que soit la taille de l'image).
 *
 * JPEG : ALLOWLIST de segments.
 *  - APP0 : seul `JFIF` est conservé, réécrit sous sa forme canonique (version,
 *    unités, densité conservées, vignette 0x0) ; `JFXX` est supprimé (il peut
 *    embarquer un JPEG avec son propre Exif).
 *  - APP2 : seulement s'il porte un profil ICC (`ICC_PROFILE\0`, multi-chunks dans
 *    l'ordre : les couleurs sont préservées). Le MPF (qui pointe vers des images
 *    secondaires) et tout autre APP2 sont retirés.
 *  - APP14 : `Adobe`, limité à ses 12 octets.
 *  - Segments de décodage : DQT, DHT, SOF*, DRI, DAC, DNL, SOS.
 *  - Tout le reste est retiré : APP1 (Exif ET XMP), APP3, APP11 (JUMBF/C2PA),
 *    APP12, APP13, APP15, COM…
 *  Les données entropiques sont parcourues (octets `FF00`, marqueurs RSTn, scans
 *  multiples) pour trouver l'EOI de l'image PRINCIPALE : tout octet APRÈS cet EOI
 *  (images MPF secondaires, trailers Samsung SEFT, vidéo des Motion Photos…) est
 *  supprimé, de même qu'un segment final tronqué ; cela compte comme « sale ».
 *  Parseur tolérant façon libjpeg pour les octets parasites entre segments
 *  (bornés), mais STRICT sur ce qui change le sens du fichier : un segment de
 *  longueur < 2 est refusé, un second SOF avant l'EOI principal est refusé, un
 *  fichier sans SOS valide est refusé, ainsi que plus de 64 scans (décode bomb
 *  progressive : le coût est linéaire en scans) ou plus de 4096 segments d'en-tête.
 *  Les runs d'octets de remplissage FF sont sautés d'un coup (strspn). Les dimensions du PREMIER SOF (ce que le
 *  décodeur décodera) sont exposées par stripReport() : elles servent à plafonner
 *  la taille de ce qui sera réellement décodé par le worker, indépendamment de
 *  getimagesize() (qui peut diverger du décodeur sur un fichier fabriqué).
 *  Si l'EXIF d'origine portait une Orientation ≠ 1, on insère un APP1 Exif
 *  MINIMAL ne contenant QUE ce tag : ni GPS, ni appareil, ni dates ne survivent.
 *
 * PNG : ALLOWLIST de chunks (IHDR, PLTE, IDAT, IEND, tRNS, cHRM, gAMA, iCCP, sBIT,
 * sRGB, cICP, mDCv, cLLi, bKGD, hIST, pHYs, sPLT, acTL, fcTL, fdAT). Tout le reste
 * (tIME, tEXt/iTXt/zTXt, caBX C2PA, chunks privés…) est retiré. Une longueur de chunk
 * déclarée plus grande que le fichier (ou qu'un IHDR ≠ 13 octets) est refusée avant toute
 * lecture. Un `eXIf` avec une
 * Orientation ≠ 1 est remplacé par un `eXIf` minimal (même TIFF canonique que
 * l'APP1 JPEG), sinon supprimé. Les octets après IEND sont supprimés.
 *
 * Le format est détecté par les octets magiques, jamais par l'extension.
 */
final class ImageMetadataStripper
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const PNG_ALLOWED_CHUNKS = [
        'IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'cHRM', 'gAMA', 'iCCP', 'sBIT', 'sRGB',
        'cICP', 'mDCv', 'cLLi', 'bKGD', 'hIST', 'pHYs', 'sPLT', 'acTL', 'fcTL', 'fdAT',
    ];

    private const EXIF_HEADER = "Exif\0\0";

    private const CHUNK = 65536;

    /** Octets parasites tolérés avant le SOS (libjpeg-like), au-delà : refus. */
    private const MAX_STRAY_BYTES = 65536;

    /**
     * Nombre maximal de scans (SOS) d'un JPEG. Le décodage coûte linéairement en scans : un
     * progressif de 4000x4000 avec 5000 scans de 53 octets tient en 453 Ko et occupe un worker
     * ~6 s (minutes à 50 MP). libjpeg n'en produit qu'une dizaine par défaut.
     */
    private const MAX_SCANS = 64;

    /** Nombre maximal de segments d'en-tête (gardés ou retirés) : un fichier honnête en a quelques dizaines. */
    private const MAX_SEGMENTS = 4096;

    /** Taille maximale d'un eXIf PNG examiné (au-delà : supprimé). */
    private const MAX_PNG_EXIF_BYTES = 65536;

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

        return self::formatOfMagic($magic);
    }

    /**
     * @return 'jpeg'|'png'|null
     */
    private static function formatOfMagic(string $magic): ?string
    {
        if (str_starts_with($magic, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        return str_starts_with($magic, self::PNG_SIGNATURE) ? 'png' : null;
    }

    /**
     * Vrai si le fichier porte au moins une métadonnée amovible (hors segments
     * d'orientation minimaux déjà canoniques).
     *
     * @throws \RuntimeException Fichier illisible, corrompu, ni JPEG ni PNG
     */
    public static function isDirty(string $path): bool
    {
        return self::process($path, null)['dirty'];
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
        return self::stripReport($path, $output)['dirty'];
    }

    /**
     * Comme strip(), avec les dimensions du premier SOF (JPEG) / de l'IHDR (PNG) de
     * l'image écrite dans $output (null si introuvables).
     *
     * @return array{dirty: bool, width: int|null, height: int|null}
     *
     * @throws \RuntimeException
     */
    public static function stripReport(string $path, string $output): array
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
     * Dimensions que le décodeur verra (premier SOF / IHDR), lues dans les en-têtes,
     * sans décoder. Null si illisibles : à traiter comme un refus (fail closed).
     *
     * @return array{int, int}|null
     */
    public static function dimensions(string $path): ?array
    {
        $format = self::format($path);
        $handle = $format === null ? false : @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            return $format === 'png' ? self::pngDimensions($handle) : self::jpegDimensions($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Comme dimensions(), à partir du contenu du fichier déjà lu en mémoire (seuls les
     * premiers Mo sont examinés).
     *
     * @return array{int, int}|null
     */
    public static function dimensionsFromBytes(string $bytes): ?array
    {
        $format = self::formatOfMagic(substr($bytes, 0, 8));
        $handle = $format === null ? false : fopen('php://temp', 'w+b');
        if ($handle === false) {
            return null;
        }

        try {
            fwrite($handle, substr($bytes, 0, 4 * 1024 * 1024));
            rewind($handle);

            return $format === 'png' ? self::pngDimensions($handle) : self::jpegDimensions($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Nettoyage en place : temp unique dans le même dossier puis rename atomique,
     * SEULEMENT si quelque chose a été retiré. L'original n'est jamais touché en cas
     * d'échec, et s'il a été supprimé ou remplacé pendant le nettoyage le temporaire
     * est jeté (rien n'est ressuscité).
     *
     * @param  \Closure|null  $afterStrip  Point d'injection pour les tests (appelé entre l'écriture et le rename)
     * @return bool Vrai si le fichier a été réécrit
     *
     * @throws \RuntimeException
     */
    public static function stripInPlace(string $path, ?\Closure $afterStrip = null): bool
    {
        $before = FileFingerprint::of($path);
        if ($before === null) {
            throw new \RuntimeException('Original not found.');
        }

        $temp = $path.'.stripping.'.bin2hex(random_bytes(6)).'.tmp';

        try {
            if (! self::strip($path, $temp)) {
                @unlink($temp);

                return false;
            }

            if ($afterStrip !== null) {
                $afterStrip();
            }

            if (FileFingerprint::of($path) !== $before) {
                throw new \RuntimeException('Original changed or removed meanwhile, cleaning discarded.');
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
     * @return array{dirty: bool, width: int|null, height: int|null}
     */
    private static function process(string $path, $out): array
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
            return $format === 'jpeg' ? self::jpeg($in, $out) : self::png($in, $out, (int) filesize($path));
        } finally {
            fclose($in);
        }
    }

    /**
     * @param  resource  $in
     * @param  resource|null  $out
     * @return array{dirty: bool, width: int|null, height: int|null}
     */
    private static function jpeg($in, $out): array
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
        $segments = 0;
        $scans = 0;
        $width = null;
        $height = null;

        while (true) {
            if (! $fill($p + 1)) {
                return self::jpegDone($sawSos, $dirty, $width, $height); // fin de fichier propre (sans EOI)
            }

            if ($sawSos) {
                // Données entropiques : FF00 (bourrage) et RSTn sont du contenu. On saute en bloc
                // jusqu'au prochain vrai marqueur (FF suivi d'autre chose), en C (preg) : rapide.
                $matched = preg_match('/\xFF[^\x00\xD0-\xD7]/', $buf, $found, PREG_OFFSET_CAPTURE, $p);
                if ($matched === false) {
                    throw new \RuntimeException('Corrupt JPEG: scan data could not be examined.');
                }

                if ($matched === 1) {
                    $at = $found[0][1];
                    if ($at > $p) {
                        self::write($out, substr($buf, $p, $at - $p));
                        $p = $at;
                    }
                } else {
                    // Aucun marqueur dans le tampon : tout est du contenu, sauf un FF final isolé
                    // (début possible d'un marqueur à cheval sur deux lectures).
                    $length = strlen($buf);
                    $hold = $buf[$length - 1] === "\xFF" ? 1 : 0;
                    self::write($out, substr($buf, $p, $length - $p - $hold));
                    $buf = $hold === 1 ? "\xFF" : '';
                    $p = 0;

                    if ($hold === 1 && ! $fill(2)) {
                        // « FF » isolé en fin de fichier : octet écarté => sale.
                        return $out === null ? ['dirty' => true, 'width' => $width, 'height' => $height] : self::jpegDone($sawSos, true, $width, $height);
                    }

                    continue;
                }
            } else {
                $ff = strpos($buf, "\xFF", $p);
                $stop = $ff === false ? strlen($buf) : $ff;

                if ($stop > $p) { // octets parasites avant le prochain marqueur
                    $stray += $stop - $p;
                    if (! self::strayOk($stray, $out, $dirty, $sawSos)) {
                        return ['dirty' => true, 'width' => $width, 'height' => $height];
                    }
                }

                if ($ff === false) {
                    $buf = '';
                    $p = 0;

                    continue;
                }
                $p = $ff;
            }

            if (! $fill($p + 2)) {
                // « FF » isolé en fin de fichier : octet écarté => sale.
                return $out === null ? ['dirty' => true, 'width' => $width, 'height' => $height] : self::jpegDone($sawSos, true, $width, $height);
            }

            $code = ord($buf[$p + 1]);

            if ($code === 0xFF) { // run d'octets de remplissage : on le saute d'un coup (le dernier FF préfixe le vrai marqueur)
                $p += max(1, strspn($buf, "\xFF", $p) - 1);

                continue;
            }

            if ($sawSos && ($code === 0x00 || ($code >= 0xD0 && $code <= 0xD7))) { // FF00 / RSTn : données entropiques
                self::write($out, substr($buf, $p, 2));
                $p += 2;

                continue;
            }

            if ($code === 0x00 || $code === 0x01 || ($code >= 0xD0 && $code <= 0xD8)) { // sans longueur, hors scan : parasite
                $stray += 2;
                if (! self::strayOk($stray, $out, $dirty, $sawSos)) {
                    return ['dirty' => true, 'width' => $width, 'height' => $height];
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
                        return ['dirty' => true, 'width' => $width, 'height' => $height];
                    }
                    $dirty = true;
                }

                return self::jpegDone($sawSos, $dirty, $width, $height);
            }

            if (! $fill($p + 4)) {
                // segment tronqué : écarté => sale
                return $out === null ? ['dirty' => true, 'width' => $width, 'height' => $height] : self::jpegDone($sawSos, true, $width, $height);
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', substr($buf, $p + 2, 2));
            $length = $unpacked[1];
            if ($length < 2) {
                // Longueur invalide : le décodeur et getimagesize() ne seraient pas d'accord sur la suite.
                throw new \RuntimeException('Corrupt JPEG: invalid segment length.');
            }
            if (! $fill($p + 2 + $length)) {
                return $out === null ? ['dirty' => true, 'width' => $width, 'height' => $height] : self::jpegDone($sawSos, true, $width, $height);
            }

            $raw = substr($buf, $p, 2 + $length);
            $payload = substr($raw, 4);
            $p += 2 + $length;

            if (++$segments > self::MAX_SEGMENTS) {
                throw new ImageRejectedException('Image non supportée : structure JPEG anormale (trop de segments).');
            }

            if (self::isSof($code)) {
                if ($width !== null) {
                    throw new \RuntimeException('Corrupt JPEG: several frame headers.');
                }
                if (strlen($payload) < 6) {
                    throw new \RuntimeException('Corrupt JPEG: bad frame header.');
                }
                /** @var array{1: int, 2: int} $dim */
                $dim = unpack('n2', substr($payload, 1, 4));
                $height = $dim[1];
                $width = $dim[2];
            }

            $newPayload = self::allowedPayload($code, $payload);

            if ($code === 0xE1 && ! $sawSos && ! $orientationDone && str_starts_with($payload, self::EXIF_HEADER)) {
                $orientation = self::exifOrientation(substr($payload, 6));

                if ($orientation !== null && $orientation > 1) {
                    $orientationDone = true;
                    $newPayload = self::minimalExifPayload($orientation); // identique à $payload si déjà canonique
                }
            }

            if ($code === 0xDA) {
                $sawSos = true;

                if (++$scans > self::MAX_SCANS) {
                    throw new ImageRejectedException('Image non supportée : trop de passes de compression JPEG.');
                }
            }

            if ($newPayload === null || $newPayload !== $payload) {
                if ($out === null) {
                    return ['dirty' => true, 'width' => $width, 'height' => $height];
                }
                $dirty = true;
            }

            if ($newPayload === null) {
                continue; // segment retiré
            }

            self::write($out, $newPayload === $payload
                ? $raw
                : "\xFF".chr($code).pack('n', strlen($newPayload) + 2).$newPayload);
        }
    }

    /**
     * @return array{dirty: bool, width: int|null, height: int|null}
     */
    private static function jpegDone(bool $sawSos, bool $dirty, ?int $width, ?int $height): array
    {
        if (! $sawSos) {
            throw new \RuntimeException('Corrupt JPEG: no valid SOS found.');
        }

        return ['dirty' => $dirty, 'width' => $width, 'height' => $height];
    }

    /**
     * Comptabilise des octets parasites avant le SOS (supprimés, donc « sale »).
     *
     * @param  resource|null  $out
     * @return bool Faux si on doit s'arrêter (détection : déjà sale)
     *
     * @throws \RuntimeException Trop d'octets parasites : pas un JPEG exploitable
     */
    private static function strayOk(int $stray, $out, bool &$dirty, bool $sawSos): bool
    {
        if ($stray > self::MAX_STRAY_BYTES) {
            throw new \RuntimeException($sawSos
                ? 'Corrupt JPEG: too many stray bytes after the scan data.'
                : 'Corrupt JPEG: no valid SOS found.');
        }
        if ($out === null) {
            return false;
        }
        $dirty = true;

        return true;
    }

    private static function isSof(int $code): bool
    {
        return $code >= 0xC0 && $code <= 0xCF && $code !== 0xC4 && $code !== 0xC8 && $code !== 0xCC;
    }

    /**
     * Allowlist des segments JPEG : payload à écrire (éventuellement réécrit), ou
     * null si le segment est retiré.
     */
    private static function allowedPayload(int $code, string $payload): ?string
    {
        return match (true) {
            // JFIF canonique : 14 octets, vignette 0x0 ; JFXX (vignette embarquée) supprimé.
            $code === 0xE0 => str_starts_with($payload, "JFIF\0") && strlen($payload) >= 14
                ? substr($payload, 0, 12)."\0\0"
                : null,
            $code === 0xE2 => str_starts_with($payload, "ICC_PROFILE\0") ? $payload : null,
            $code === 0xEE => str_starts_with($payload, 'Adobe') && strlen($payload) >= 12 ? substr($payload, 0, 12) : null,
            $code === 0xDB, $code === 0xDD, $code === 0xDC, $code === 0xDA, self::isSof($code), $code === 0xC4, $code === 0xCC => $payload,
            default => null,
        };
    }

    /**
     * @param  resource  $in
     * @param  resource|null  $out
     * @return array{dirty: bool, width: int|null, height: int|null}
     */
    private static function png($in, $out, int $fileSize): array
    {
        self::write($out, (string) fread($in, 8));
        $dirty = false;
        $orientationDone = false;
        $width = null;
        $height = null;

        while (true) {
            $header = (string) fread($in, 8);
            if (strlen($header) < 8) {
                return ['dirty' => $dirty, 'width' => $width, 'height' => $height];
            }

            /** @var array{1: int} $unpacked */
            $unpacked = unpack('N', substr($header, 0, 4));
            $length = $unpacked[1];
            $type = substr($header, 4, 4);
            $rest = $length + 4; // données + CRC

            // Une longueur déclarée absurde ne doit jamais piloter une allocation ou un fseek.
            if ($length > 0x7FFFFFFF || $rest > $fileSize - (int) ftell($in)) {
                throw new \RuntimeException('Corrupt PNG: chunk length exceeds the file.');
            }
            if ($type === 'IHDR' && $length !== 13) {
                throw new \RuntimeException('Corrupt PNG: IHDR must be 13 bytes.');
            }

            if ($type === 'eXIf' && ! $orientationDone && $length <= self::MAX_PNG_EXIF_BYTES) {
                $raw = (string) fread($in, $rest);
                if (strlen($raw) !== $rest) {
                    throw new \RuntimeException('Corrupt PNG: truncated chunk.');
                }
                $data = substr($raw, 0, $length);
                $orientation = self::exifOrientation($data);

                if ($orientation !== null && $orientation > 1) {
                    $orientationDone = true;
                    $minimal = substr(self::minimalExifPayload($orientation), strlen(self::EXIF_HEADER));

                    if ($data === $minimal) {
                        self::write($out, $header.$raw); // déjà canonique : idempotent

                        continue;
                    }

                    if ($out === null) {
                        return ['dirty' => true, 'width' => $width, 'height' => $height];
                    }
                    $dirty = true;
                    self::write($out, pack('N', strlen($minimal)).'eXIf'.$minimal.pack('N', crc32('eXIf'.$minimal)));

                    continue;
                }

                if ($out === null) {
                    return ['dirty' => true, 'width' => $width, 'height' => $height];
                }
                $dirty = true;

                continue;
            }

            if (! in_array($type, self::PNG_ALLOWED_CHUNKS, true)) {
                if ($out === null) {
                    return ['dirty' => true, 'width' => $width, 'height' => $height];
                }
                $dirty = true;
                fseek($in, $rest, SEEK_CUR);

                continue;
            }

            if ($type === 'IHDR' && $width === null) {
                $data = (string) fread($in, $length);
                $crc = (string) fread($in, 4);
                if (strlen($data) !== $length || strlen($crc) !== 4) {
                    throw new \RuntimeException('Corrupt PNG: truncated IHDR.');
                }
                /** @var array{1: int, 2: int} $dim */
                $dim = unpack('N2', substr($data, 0, 8));
                $width = $dim[1];
                $height = $dim[2];
                self::write($out, $header.$data.$crc);
            } elseif ($out !== null) {
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
                        return ['dirty' => true, 'width' => $width, 'height' => $height];
                    }
                    $dirty = true;
                }

                return ['dirty' => $dirty, 'width' => $width, 'height' => $height];
            }
        }
    }

    /**
     * Orientation EXIF (2 à 8) portée par le chunk eXIf d'un PNG, ou null. Intervention
     * n'applique l'orientation EXIF qu'aux JPEG/TIFF : pour un PNG, c'est à l'appelant de
     * l'appliquer après décodage.
     */
    public static function pngOrientation(string $bytes): ?int
    {
        if (! str_starts_with($bytes, self::PNG_SIGNATURE)) {
            return null;
        }

        $offset = 8;
        $size = strlen($bytes);
        while ($offset + 8 <= $size) {
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('N', substr($bytes, $offset, 4));
            $length = $unpacked[1];
            $type = substr($bytes, $offset + 4, 4);

            if ($type === 'IDAT' || $type === 'IEND') {
                return null;
            }
            if ($type === 'eXIf' && $length <= self::MAX_PNG_EXIF_BYTES && $offset + 8 + $length <= $size) {
                $orientation = self::exifOrientation(substr($bytes, $offset + 8, $length));

                return $orientation !== null && $orientation > 1 ? $orientation : null;
            }

            $offset += 12 + $length;
        }

        return null;
    }

    /**
     * @param  resource  $handle
     * @return array{int, int}|null
     */
    private static function pngDimensions($handle): ?array
    {
        $head = (string) fread($handle, 24);
        if (strlen($head) < 24 || substr($head, 12, 4) !== 'IHDR') {
            return null;
        }

        /** @var array{1: int, 2: int} $dim */
        $dim = unpack('N2', substr($head, 16, 8));

        return [$dim[1], $dim[2]];
    }

    /**
     * Premier SOF d'un JPEG, avec la même tolérance que le décodeur (octets parasites,
     * remplissage) mais refus de toute longueur invalide. S'arrête au SOS.
     *
     * @param  resource  $handle
     * @return array{int, int}|null
     */
    private static function jpegDimensions($handle): ?array
    {
        fseek($handle, 2);
        $budget = 4 * 1024 * 1024; // en-têtes seulement : borne dure

        while ($budget-- > 0) {
            $byte = fread($handle, 1);
            if ($byte === false || $byte === '') {
                return null;
            }
            if ($byte !== "\xFF") {
                continue; // octet parasite
            }

            do {
                $marker = fread($handle, 1);
                if ($marker === false || $marker === '') {
                    return null;
                }
            } while ($marker === "\xFF");

            $code = ord($marker);
            if ($code === 0x00 || $code === 0x01 || ($code >= 0xD0 && $code <= 0xD8)) {
                continue;
            }
            if ($code === 0xD9 || $code === 0xDA) {
                return null; // EOI/SOS avant tout SOF
            }

            $lengthBytes = (string) fread($handle, 2);
            if (strlen($lengthBytes) < 2) {
                return null;
            }
            /** @var array{1: int} $unpacked */
            $unpacked = unpack('n', $lengthBytes);
            $length = $unpacked[1];
            if ($length < 2) {
                return null;
            }

            if (self::isSof($code)) {
                $payload = (string) fread($handle, min($length - 2, 5));
                if (strlen($payload) < 5) {
                    return null;
                }
                /** @var array{1: int, 2: int} $dim */
                $dim = unpack('n2', substr($payload, 1, 4));

                return [$dim[2], $dim[1]];
            }

            if (fseek($handle, $length - 2, SEEK_CUR) !== 0) {
                return null;
            }
        }

        return null;
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
