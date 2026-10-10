<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ImageMetadataStripper;
use Tests\Concerns\BuildsMediaFixtures;
use Tests\TestCase;

/**
 * Nettoyage sans perte des métadonnées image (octets, aucun décodage).
 */
class ImageMetadataStripperTest extends TestCase
{
    use BuildsMediaFixtures;

    private const ICC = "ICC_PROFILE\0\x01\x01FAKE-ICC-PROFILE-BYTES";

    private function tmp(string $bytes, string $suffix = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ims').$suffix;
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * Liste [code marqueur => payload] des segments d'en-tête (jusqu'au SOS).
     *
     * @return list<array{int, string}>
     */
    private function segments(string $jpeg): array
    {
        $segments = [];
        $pos = 2;
        while ($pos < strlen($jpeg) && $jpeg[$pos] === "\xFF") {
            $code = ord($jpeg[$pos + 1]);
            if ($code === 0xDA) {
                break;
            }
            $len = unpack('n', substr($jpeg, $pos + 2, 2))[1];
            $segments[] = [$code, substr($jpeg, $pos + 4, $len - 2)];
            $pos += 2 + $len;
        }

        return $segments;
    }

    private function afterSos(string $jpeg): string
    {
        $pos = 2;
        while ($jpeg[$pos + 1] !== "\xDA") {
            $pos += 2 + unpack('n', substr($jpeg, $pos + 2, 2))[1];
        }

        return substr($jpeg, $pos);
    }

    private function dirtyJpeg(int $orientation): string
    {
        return $this->withSegments(
            $this->plainJpeg(40, 20),
            $this->app1Exif($orientation),
            $this->segment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>GPS</x:xmpmeta>"),
            $this->segment(0xED, "Photoshop 3.0\0 iptc"),
            $this->segment(0xFE, '<html>comment</html>'),
            $this->segment(0xE2, self::ICC),
            $this->segment(0xEE, "Adobe\0\x64\0\0\0\0\x01"),
        );
    }

    public function test_jpeg_strip_is_lossless_keeps_icc_and_orientation_only(): void
    {
        $original = $this->dirtyJpeg(6);
        $in = $this->tmp($original);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $codes = array_map(fn (array $s): int => $s[0], $this->segments($result));

        $this->assertContains(0xE0, $codes, 'JFIF conservé');
        $this->assertContains(0xE2, $codes, 'ICC conservé');
        $this->assertContains(0xEE, $codes, 'Adobe APP14 conservé');
        $this->assertNotContains(0xED, $codes);
        $this->assertNotContains(0xFE, $codes);
        $this->assertSame(1, count(array_filter($codes, fn (int $c): bool => $c === 0xE1)), 'un seul APP1 : l\'Exif minimal');

        $icc = array_values(array_filter($this->segments($result), fn (array $s): bool => $s[0] === 0xE2));
        $this->assertSame(self::ICC, $icc[0][1]);

        // Pixels / flux compressé strictement identiques (lossless)
        $this->assertSame($this->afterSos($original), $this->afterSos($result));

        // EXIF minimal : Orientation=6 et RIEN d'autre (ni GPS, ni appareil)
        $exif = exif_read_data($out);
        $this->assertSame(6, $exif['Orientation']);
        foreach (['GPSLatitude', 'GPSLatitudeRef', 'GPSInfo', 'Make', 'Model', 'DateTime', 'DateTimeOriginal', 'Software'] as $key) {
            $this->assertArrayNotHasKey($key, $exif);
        }
        $this->assertStringNotContainsString('Canon', $result);
        $this->assertStringNotContainsString('xmpmeta', $result);

        // Toujours décodable, mêmes dimensions
        $this->assertSame([40, 20], array_slice(getimagesize($out), 0, 2));
        $this->assertNotFalse(imagecreatefromjpeg($out));
    }

    public function test_jpeg_without_orientation_gets_no_exif_at_all_and_second_pass_is_clean(): void
    {
        $in = $this->tmp($this->dirtyJpeg(1));
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::strip($in, $out));
        $codes = array_map(fn (array $s): int => $s[0], $this->segments((string) file_get_contents($out)));
        $this->assertNotContains(0xE1, $codes);

        $this->assertFalse(ImageMetadataStripper::isDirty($out));
        $this->assertFalse(ImageMetadataStripper::strip($out, $out.'2'), 'rien à retirer');
    }

    public function test_canonical_minimal_exif_is_idempotent(): void
    {
        $in = $this->tmp($this->dirtyJpeg(8));
        $once = $in.'.1';
        ImageMetadataStripper::strip($in, $once);

        $this->assertFalse(ImageMetadataStripper::isDirty($once));

        $twice = $in.'.2';
        ImageMetadataStripper::strip($once, $twice);
        $this->assertSame(file_get_contents($once), file_get_contents($twice));
    }

    public function test_format_is_detected_by_magic_bytes_not_extension(): void
    {
        $this->assertSame('jpeg', ImageMetadataStripper::format($this->tmp($this->plainJpeg(4, 4), '.html')));
        $this->assertNull(ImageMetadataStripper::format($this->tmp('<html></html>', '.jpg')));
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * @return list<string> types de chunks dans l'ordre
     */
    private function pngChunks(string $png): array
    {
        $types = [];
        $pos = 8;
        while ($pos < strlen($png)) {
            $len = unpack('N', substr($png, $pos, 4))[1];
            $types[] = substr($png, $pos + 4, 4);
            $pos += 12 + $len;
        }

        return $types;
    }

    public function test_png_text_chunks_removed_and_icc_kept(): void
    {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        // Insère après IHDR (8 sig + 25 chunk IHDR)
        $extra = $this->chunk('iCCP', "icc\0\0FAKE")
            .$this->chunk('tEXt', "GPS\0 6.3703,2.3912")
            .$this->chunk('iTXt', "XML:com.adobe.xmp\0\0\0\0\0<x/>")
            .$this->chunk('zTXt', "Comment\0\0xx")
            .$this->chunk('eXIf', $this->exifTiff(6))
            .$this->chunk('pHYs', pack('NNC', 2835, 2835, 1));
        $dirty = substr($png, 0, 33).$extra.substr($png, 33);

        $in = $this->tmp($dirty);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $types = $this->pngChunks((string) file_get_contents($out));
        $this->assertContains('iCCP', $types);
        $this->assertContains('pHYs', $types);
        $this->assertContains('IDAT', $types);
        $this->assertSame('IEND', end($types));
        $this->assertContains('eXIf', $types, 'orientation 6 : eXIf minimal conservé');
        $this->assertStringNotContainsString('Canon', (string) file_get_contents($out));
        foreach (['tEXt', 'iTXt', 'zTXt'] as $dropped) {
            $this->assertNotContains($dropped, $types);
        }
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
        $this->assertNotFalse(imagecreatefrompng($out));
    }

    public function test_corrupt_jpeg_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        ImageMetadataStripper::strip($this->tmp("\xFF\xD8\xFF\xE1\xFF\xFFshort"), sys_get_temp_dir().'/never.out');
    }

    public function test_strip_in_place_keeps_original_untouched_on_failure(): void
    {
        $bytes = "\xFF\xD8\xFF\xE1\xFF\xFFshort";
        $path = $this->tmp($bytes);

        try {
            ImageMetadataStripper::stripInPlace($path);
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertSame($bytes, file_get_contents($path));
            $this->assertFileDoesNotExist($path.'.stripping.tmp');
        }
    }

    public function test_memory_stays_constant_on_a_large_file(): void
    {
        // JPEG « décodé » de ~48 Mo de scan (non décodable, le stripper ne décode jamais).
        $path = tempnam(sys_get_temp_dir(), 'big');
        $h = fopen($path, 'wb');
        fwrite($h, "\xFF\xD8".$this->app1Exif(6).$this->segment(0xE2, self::ICC)."\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00");
        // Unité de 1009 octets : bourrage FF00, RSTn et FF de remplissage avant un marqueur
        // (1 octet supprimé par unité), comme dans un vrai flux entropique.
        $unit = str_repeat('0123456789', 100)."\xFF\x00cd\xFF\xD0\xFF\xFF\xD1";
        $block = str_repeat($unit, 1040); // ~1 Mo
        for ($i = 0; $i < 48; $i++) {
            fwrite($h, $block);
        }
        fwrite($h, "\xFF\xD9");
        fclose($h);
        unset($block);
        $units = 48 * 1040;

        $out = $path.'.out';
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        ImageMetadataStripper::strip($path, $out);

        $delta = memory_get_peak_usage() - $before;
        $this->assertLessThan(4 * 1024 * 1024, $delta, "pic mémoire {$delta} octets sur un fichier de 48 Mo");
        $this->assertSame(filesize($path) - strlen($this->app1Exif(6)) + 36 - $units, filesize($out), 'FF de remplissage retirés, FF00/RSTn intacts');
    }

    public function test_big_endian_exif_orientation_is_preserved_in_the_minimal_segment(): void
    {
        $tiff = $this->exifTiffBigEndian(6);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
        $in = $this->tmp($this->withSegments($this->plainJpeg(40, 20), $app1));
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $exif = exif_read_data($out);
        $this->assertSame(6, $exif['Orientation']);
        $this->assertArrayNotHasKey('GPSInfo', $exif);
    }

    /**
     * JPEG GD réellement PROPRE : on le passe d'abord par le stripper (GD écrit un COM
     * « CREATOR: gd-jpeg »), puis on vérifie qu'il est propre.
     */
    private function cleanJpeg(int $w = 40, int $h = 20): string
    {
        $in = $this->tmp($this->plainJpeg($w, $h));
        ImageMetadataStripper::strip($in, $in.'.clean');
        $this->assertFalse(ImageMetadataStripper::isDirty($in.'.clean'), 'fixture de base propre');

        return (string) file_get_contents($in.'.clean');
    }

    public function test_trailing_bytes_after_eoi_are_the_only_reason_for_dirty(): void
    {
        $clean = $this->cleanJpeg();

        $this->assertFalse(ImageMetadataStripper::isDirty($this->tmp($clean)));
        $this->assertTrue(ImageMetadataStripper::isDirty($this->tmp($clean."\0\0\0\x18ftypmp42 SECRET")));
    }

    private function motionPhoto(): string
    {
        $secondary = $this->withSegments($this->plainJpeg(8, 8), $this->app1Exif(3)); // image secondaire AVEC son Exif/GPS
        $mpf = $this->segment(0xE2, "MPF\0".str_repeat("\x01", 20));                  // APP2 MPF : pointe vers l'image secondaire
        $primary = $this->withSegments($this->cleanJpeg(), $this->segment(0xE2, self::ICC), $mpf);

        return $primary.$secondary."\0\0\0\x18ftypmp42 SECRET-MOTION-VIDEO";
    }

    public function test_data_after_eoi_and_mpf_segment_are_removed_and_flagged_dirty(): void
    {
        $original = $this->motionPhoto();
        $in = $this->tmp($original);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in), 'les octets après EOI doivent être détectés par la commande de rétrofit');
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $this->assertStringEndsWith("\xFF\xD9", $result, 'tronqué à l\'EOI de l\'image principale');
        $this->assertStringNotContainsString('SECRET-MOTION-VIDEO', $result);
        $this->assertStringNotContainsString('Canon', $result);
        $this->assertStringNotContainsString('MPF', $result);
        $codes = array_map(fn (array $s): int => $s[0], $this->segments($result));
        $this->assertSame(1, count(array_filter($codes, fn (int $c): bool => $c === 0xE2)), 'seul l\'ICC reste en APP2');
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
        $this->assertNotFalse(imagecreatefromjpeg($out));
    }

    public function test_entropy_data_with_stuffing_and_restart_markers_is_not_mistaken_for_eoi(): void
    {
        $scan = "AB\xFF\x00CD\xFF\xD0EF\xFF\x00\xFF\xD1G"; // FF00 = octet FF, RSTn
        $head = "\xFF\xD8".$this->segment(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0");
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        $jpeg = $head.$sos.$scan."\xFF\xFF\xD9".'TRAILING-GPS'; // octet de remplissage FF avant l'EOI

        $in = $this->tmp($jpeg);
        $out = $in.'.out';
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $this->assertSame($head.$sos.$scan."\xFF\xD9", (string) file_get_contents($out));
    }

    public function test_progressive_style_scans_keep_tables_and_drop_comments_between_scans(): void
    {
        $head = "\xFF\xD8".$this->segment(0xDB, str_repeat("\x01", 65));
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        $dht = $this->segment(0xC4, str_repeat("\x02", 20));
        $jpeg = $head.$sos.'SCAN1'.$this->segment(0xFE, 'comment GPS').$dht.$this->segment(0xEB, 'APP11 C2PA').$sos."SCAN2\xFF\xD9";

        $in = $this->tmp($jpeg);
        $out = $in.'.out';
        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $this->assertSame($head.$sos.'SCAN1'.$dht.$sos."SCAN2\xFF\xD9", $result);
    }

    public function test_only_allowlisted_segments_survive_and_multi_chunk_icc_keeps_its_order(): void
    {
        $icc1 = "ICC_PROFILE\0\x01\x02CHUNK-ONE";
        $icc2 = "ICC_PROFILE\0\x02\x02CHUNK-TWO";
        $jpeg = $this->withSegments(
            $this->plainJpeg(40, 20),
            $this->segment(0xE3, 'APP3 Meta'),
            $this->segment(0xE2, $icc1),
            $this->segment(0xEB, 'JUMBF c2pa manifest'),
            $this->segment(0xEC, 'APP12 Ducky'),
            $this->segment(0xED, 'APP13 8BIM'),
            $this->segment(0xEF, 'APP15'),
            $this->segment(0xE2, "MPF\0whatever"),
            $this->segment(0xE2, $icc2),
            $this->segment(0xFE, 'comment'),
            $this->segment(0xEE, "Adobe\0\x64\0\0\0\0\x01"),
        );
        $in = $this->tmp($jpeg);
        $out = $in.'.out';
        ImageMetadataStripper::strip($in, $out);

        $segments = $this->segments((string) file_get_contents($out));
        $codes = array_map(fn (array $s): int => $s[0], $segments);

        foreach ([0xE3, 0xEB, 0xEC, 0xED, 0xEF, 0xFE, 0xE1] as $dropped) {
            $this->assertNotContains($dropped, $codes, sprintf('APP/COM 0x%02X doit être retiré', $dropped));
        }
        $this->assertContains(0xE0, $codes);
        $this->assertContains(0xEE, $codes);
        $icc = array_values(array_filter($segments, fn (array $s): bool => $s[0] === 0xE2));
        $this->assertSame([$icc1, $icc2], array_map(fn (array $s): string => $s[1], $icc));
    }

    public function test_stray_bytes_between_header_segments_are_tolerated_and_removed(): void
    {
        $jpeg = $this->withSegments($this->plainJpeg(40, 20), $this->segment(0xE2, self::ICC)."\x00\x00JUNK\x00");
        $in = $this->tmp($jpeg);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $this->assertStringNotContainsString('JUNK', $result);
        $this->assertSame([40, 20], array_slice(getimagesize($out), 0, 2));
        $this->assertNotFalse(imagecreatefromjpeg($out));
    }

    public function test_file_without_any_valid_sos_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        ImageMetadataStripper::strip($this->tmp("\xFF\xD8\xFF\xE0\x00\x04ab".str_repeat('x', 200)), sys_get_temp_dir().'/never.out');
    }

    public function test_png_data_after_iend_is_flagged_and_dropped(): void
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean().'TRAILING-SECRET';
        $in = $this->tmp($png);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        ImageMetadataStripper::strip($in, $out);
        $this->assertStringNotContainsString('TRAILING-SECRET', (string) file_get_contents($out));
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
    }

    private const SOS_MARKER = "\xFF\xDA";

    private const SOS = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";

    private function sof(int $width, int $height, int $code = 0xC0): string
    {
        return $this->segment($code, pack('CnnC', 8, $height, $width, 1)."\x01\x11\x00");
    }

    private function bareJpeg(string ...$parts): string
    {
        return "\xFF\xD8".implode('', $parts);
    }

    private function dqt(): string
    {
        return $this->segment(0xDB, "\x00".str_repeat("\x01", 64));
    }

    private function dht(): string
    {
        return $this->segment(0xC4, "\x00".str_repeat("\x00", 16));
    }

    public function test_zero_length_segment_is_a_hard_reject_not_stray_bytes(): void
    {
        // (a) FFD8 FFE1 0000 + DQT + SOF0(30000x30000) + DHT + SOS + données + EOI
        $jpeg = "\xFF\xD8\xFF\xE1\x00\x00".$this->dqt().$this->sof(30000, 30000).$this->dht().self::SOS.str_repeat("\0", 64)."\xFF\xD9";

        $this->expectException(\RuntimeException::class);
        ImageMetadataStripper::strip($this->tmp($jpeg), sys_get_temp_dir().'/never.out');
    }

    public function test_dimensions_of_the_first_sof_are_reported_even_when_a_decoy_sof_hides_behind_a_fake_length(): void
    {
        // (b) FFD8 FF01 <len> [DQT, SOF 30000², DHT, SOS, data, EOI] puis un SOF 100x100
        $inner = $this->dqt().$this->sof(30000, 30000).$this->dht().self::SOS.str_repeat("\0", 64)."\xFF\xD9";
        $jpeg = "\xFF\xD8\xFF\x01".pack('n', 2 + strlen($inner)).$inner.$this->sof(100, 100);

        // getimagesize (PHP) se laisse tromper : c'est la divergence exploitée.
        $this->assertSame([100, 100], array_slice(getimagesize($this->tmp($jpeg)) ?: [], 0, 2));

        $in = $this->tmp($jpeg);
        $report = ImageMetadataStripper::stripReport($in, $in.'.out');

        $this->assertSame([30000, 30000], [$report['width'], $report['height']], 'on mesure ce que le décodeur décodera');
        $this->assertSame([30000, 30000], ImageMetadataStripper::dimensions($in.'.out'));
    }

    public function test_a_second_sof_before_the_primary_eoi_is_rejected(): void
    {
        $jpeg = $this->bareJpeg($this->dqt(), $this->sof(100, 100), $this->sof(30000, 30000), $this->dht(), self::SOS, "\0\0\xFF\xD9");

        $this->expectException(\RuntimeException::class);
        ImageMetadataStripper::strip($this->tmp($jpeg), sys_get_temp_dir().'/never.out');
    }

    public function test_dimensions_helper_reads_jpeg_and_png_headers_and_returns_null_when_unreadable(): void
    {
        $this->assertSame([40, 20], ImageMetadataStripper::dimensions($this->tmp($this->plainJpeg(40, 20))));

        $im = imagecreatetruecolor(7, 5);
        ob_start();
        imagepng($im);
        $this->assertSame([7, 5], ImageMetadataStripper::dimensions($this->tmp((string) ob_get_clean())));

        $this->assertNull(ImageMetadataStripper::dimensions($this->tmp('<html></html>')));
        $this->assertNull(ImageMetadataStripper::dimensions($this->tmp($this->bareJpeg($this->dqt(), self::SOS, "\0\xFF\xD9"))), 'JPEG sans SOF');
    }

    private function pngWith(string ...$extraChunks): string
    {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        return substr($png, 0, 33).implode('', $extraChunks).substr($png, 33);
    }

    public function test_png_uses_an_allowlist_time_c2pa_and_private_chunks_are_removed(): void
    {
        $in = $this->tmp($this->pngWith(
            $this->chunk('tIME', pack('nCCCCC', 2026, 10, 7, 12, 0, 0)),
            $this->chunk('caBX', 'C2PA-IDENTITY-GPS'),
            $this->chunk('prVt', 'PRIVATE-SECRET'),
        ));
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $types = $this->pngChunks($result);
        foreach (['tIME', 'caBX', 'prVt'] as $dropped) {
            $this->assertNotContains($dropped, $types);
        }
        $this->assertStringNotContainsString('SECRET', $result);
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
    }

    public function test_png_animation_and_color_chunks_are_kept_in_order(): void
    {
        $kept = [
            $this->chunk('cICP', "\x01\x0D\x00\x01"),
            $this->chunk('acTL', pack('NN', 1, 0)),
            $this->chunk('fcTL', str_repeat("\0", 26)),
            $this->chunk('sBIT', "\x08\x08\x08"),
            $this->chunk('bKGD', "\0\0\0\0\0\0"),
        ];
        $in = $this->tmp($this->pngWith(...$kept));
        $out = $in.'.out';

        $this->assertFalse(ImageMetadataStripper::isDirty($in));
        ImageMetadataStripper::strip($in, $out);

        $types = $this->pngChunks((string) file_get_contents($out));
        $this->assertSame(['IHDR', 'cICP', 'acTL', 'fcTL', 'sBIT', 'bKGD'], array_slice($types, 0, 6));
        $this->assertSame((string) file_get_contents($in), (string) file_get_contents($out));
    }

    public function test_png_exif_keeps_only_a_minimal_orientation_chunk(): void
    {
        $in = $this->tmp($this->pngWith($this->chunk('eXIf', $this->exifTiff(6))));
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::strip($in, $out));
        $result = (string) file_get_contents($out);

        $this->assertContains('eXIf', $this->pngChunks($result));
        $this->assertStringNotContainsString('Canon', $result);
        $this->assertFalse(ImageMetadataStripper::isDirty($out), 'eXIf minimal canonique = idempotent');

        $exifPayload = $this->pngChunkData($result, 'eXIf');
        $this->assertSame(26, strlen($exifPayload));
        $this->assertSame("II*\0", substr($exifPayload, 0, 4));
        $this->assertSame(6, unpack('v', substr($exifPayload, 18, 2))[1], 'Orientation=6');

        // Orientation 1 : eXIf supprimé
        $in2 = $this->tmp($this->pngWith($this->chunk('eXIf', $this->exifTiff(1))));
        ImageMetadataStripper::strip($in2, $in2.'.out');
        $this->assertNotContains('eXIf', $this->pngChunks((string) file_get_contents($in2.'.out')));
    }

    private function pngChunkData(string $png, string $type): string
    {
        $pos = 8;
        while ($pos < strlen($png)) {
            $len = unpack('N', substr($png, $pos, 4))[1];
            if (substr($png, $pos + 4, 4) === $type) {
                return substr($png, $pos + 8, $len);
            }
            $pos += 12 + $len;
        }

        return '';
    }

    public function test_png_dimensions_are_reported_from_ihdr(): void
    {
        $in = $this->tmp($this->pngWith());
        $report = ImageMetadataStripper::stripReport($in, $in.'.out');

        $this->assertSame([8, 8], [$report['width'], $report['height']]);
    }

    public function test_jfif_is_rewritten_canonical_without_thumbnail_and_jfxx_is_dropped(): void
    {
        $jfifWithThumb = $this->segment(0xE0, "JFIF\0\x01\x02\x01\x00\x48\x00\x48\x02\x02".str_repeat("\x7F", 12)); // vignette 2x2 RGB
        $jfxx = $this->segment(0xE0, "JFXX\0\x10".'GPS-LAT-12.34-SECRET');
        $adobe = $this->segment(0xEE, "Adobe\0\x64\0\0\0\0\x01".'TRAILING-SECRET');
        $jpeg = $this->bareJpeg($jfifWithThumb, $jfxx, $adobe, $this->dqt(), $this->sof(8, 8), $this->dht(), self::SOS, "\0\0\xFF\xD9");
        $in = $this->tmp($jpeg);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in));
        $this->assertTrue(ImageMetadataStripper::strip($in, $out));

        $result = (string) file_get_contents($out);
        $segments = $this->segments($result);
        $app0 = array_values(array_filter($segments, fn (array $s): bool => $s[0] === 0xE0));
        $this->assertCount(1, $app0, 'JFXX supprimé');
        $this->assertSame("JFIF\0\x01\x02\x01\x00\x48\x00\x48\x00\x00", $app0[0][1], 'JFIF canonique : version, unités, densité conservées, vignette 0x0');
        $adobeOut = array_values(array_filter($segments, fn (array $s): bool => $s[0] === 0xEE));
        $this->assertSame(12, strlen($adobeOut[0][1]), 'APP14 limité à 12 octets');
        $this->assertStringNotContainsString('SECRET', $result);
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
    }

    public function test_truncated_final_segment_is_flagged_dirty_in_both_modes_and_discarded(): void
    {
        // données de scan sans EOI, puis un segment COM tronqué contenant du texte
        $jpeg = $this->bareJpeg($this->dqt(), $this->sof(8, 8), $this->dht(), self::SOS, "SCANDATA\xFF\xFE\x7F\xFFGPS 6.37,2.39 SECRET");
        $in = $this->tmp($jpeg);
        $out = $in.'.out';

        $this->assertTrue(ImageMetadataStripper::isDirty($in), 'détection');
        $this->assertTrue(ImageMetadataStripper::strip($in, $out), 'application');
        $this->assertStringNotContainsString('SECRET', (string) file_get_contents($out));
        $this->assertFalse(ImageMetadataStripper::isDirty($out));
    }

    public function test_strip_in_place_does_not_resurrect_a_file_deleted_during_the_strip(): void
    {
        $path = $this->tmp($this->jpegWithExif(40, 20, 6));

        try {
            ImageMetadataStripper::stripInPlace($path, function () use ($path): void {
                unlink($path); // supprimé pendant le nettoyage
            });
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($path);
            $this->assertSame([], glob($path.'.*'));
        }
    }

    public function test_strip_in_place_leaves_a_file_replaced_during_the_strip_untouched(): void
    {
        $path = $this->tmp($this->jpegWithExif(40, 20, 6));

        try {
            ImageMetadataStripper::stripInPlace($path, function () use ($path): void {
                unlink($path);
                file_put_contents($path, 'NEWER-FILE-CONTENT-DIFFERENT-SIZE');
            });
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            $this->assertSame('NEWER-FILE-CONTENT-DIFFERENT-SIZE', file_get_contents($path));
            $this->assertSame([], glob($path.'.*'));
        }
    }

    public function test_strip_in_place_temp_names_are_unique(): void
    {
        $path = $this->tmp($this->jpegWithExif(40, 20, 6));
        $seen = [];
        try {
            ImageMetadataStripper::stripInPlace($path, function () use ($path, &$seen): void {
                $seen = glob($path.'.*');
                throw new \RuntimeException('stop');
            });
        } catch (\RuntimeException) {
        }

        $this->assertCount(1, $seen);
        $this->assertMatchesRegularExpression('/\.stripping\.[a-f0-9]{8,}\.tmp$/', $seen[0]);
    }

    private function scans(int $count): string
    {
        $scan = self::SOS."\x00"; // le scan le moins cher possible
        $jpeg = $this->bareJpeg($this->dqt(), $this->sof(8, 8, 0xC2), $this->dht()).str_repeat($scan, $count)."\xFF\xD9";

        return $jpeg;
    }

    public function test_more_than_64_scans_are_rejected_decode_bomb(): void
    {
        $this->assertNotNull(ImageMetadataStripper::stripReport($this->tmp($this->scans(64)), sys_get_temp_dir().'/scans64.out')['width']);

        try {
            ImageMetadataStripper::strip($this->tmp($this->scans(65)), sys_get_temp_dir().'/scans65.out');
            $this->fail('exception attendue');
        } catch (\App\Support\ImageRejectedException $e) {
            $this->assertStringContainsString('Image non supportée', $e->getMessage());
        }
    }

    public function test_a_normal_progressive_jpeg_is_accepted(): void
    {
        $im = imagecreatetruecolor(64, 48);
        imageinterlace($im, true);
        ob_start();
        imagejpeg($im, null, 85);
        $jpeg = (string) ob_get_clean();
        $this->assertGreaterThan(1, substr_count($jpeg, self::SOS_MARKER), 'fixture progressive (plusieurs scans)');

        $in = $this->tmp($jpeg);
        ImageMetadataStripper::strip($in, $in.'.out');

        $this->assertNotFalse(imagecreatefromjpeg($in.'.out'));
    }

    public function test_png_with_a_huge_declared_chunk_length_is_rejected_without_allocating(): void
    {
        $sig = "\x89PNG\r\n\x1a\n";
        $path = $this->tmp($sig.pack('N', 0x7FFFFFF0).'IHDR'.pack('NN', 100, 100).str_repeat("\0", 16));

        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        try {
            ImageMetadataStripper::strip($path, sys_get_temp_dir().'/huge.out');
            $this->fail('exception attendue');
        } catch (\RuntimeException) {
            // attendu
        }

        // Une allocation pilotée par la longueur déclarée (~2 Go) ferait exploser ce pic.
        $this->assertLessThan(4 * 1024 * 1024, memory_get_peak_usage() - $before);
    }

    public function test_png_ihdr_must_be_exactly_13_bytes_and_chunks_cannot_exceed_the_file(): void
    {
        $sig = "\x89PNG\r\n\x1a\n";

        foreach ([
            $sig.$this->chunk('IHDR', str_repeat("\0", 14)),
            $sig.$this->chunk('IHDR', pack('NNCCCCC', 8, 8, 8, 2, 0, 0, 0)).pack('N', 5000).'IDATshort',
        ] as $bad) {
            try {
                ImageMetadataStripper::strip($this->tmp($bad), sys_get_temp_dir().'/bad.out');
                $this->fail('exception attendue');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_large_idat_chunk_is_streamed_with_bounded_memory(): void
    {
        $ihdr = $this->chunk('IHDR', pack('NNCCCCC', 8, 8, 8, 2, 0, 0, 0));
        $path = tempnam(sys_get_temp_dir(), 'bigpng');
        $h = fopen($path, 'wb');
        $data = str_repeat("\0", 1024 * 1024);
        fwrite($h, "\x89PNG\r\n\x1a\n".$ihdr.pack('N', 24 * 1024 * 1024).'IDAT');
        for ($i = 0; $i < 24; $i++) {
            fwrite($h, $data);
        }
        fwrite($h, pack('N', 0).$this->chunk('IEND', ''));
        fclose($h);
        unset($data);

        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();
        ImageMetadataStripper::strip($path, $path.'.out');

        $this->assertLessThan(4 * 1024 * 1024, memory_get_peak_usage() - $before);
        $this->assertSame(filesize($path), filesize($path.'.out'));
    }

    public function test_fill_byte_runs_are_skipped_quickly_before_and_after_the_scan(): void
    {
        $fill = str_repeat("\xFF", 8 * 1024 * 1024);
        $afterScan = $this->bareJpeg($this->dqt(), $this->sof(8, 8), $this->dht(), self::SOS, 'DATA', $fill)."\xD9";
        $beforeHeaders = "\xFF\xD8".$fill.substr($this->bareJpeg($this->dqt(), $this->sof(8, 8), $this->dht(), self::SOS, 'DATA', "\xFF\xD9"), 2);

        $start = microtime(true);
        foreach ([$afterScan, $beforeHeaders] as $jpeg) {
            $in = $this->tmp($jpeg);
            ImageMetadataStripper::strip($in, $in.'.out');
            $out = (string) file_get_contents($in.'.out');
            $this->assertStringEndsWith("DATA\xFF\xD9", $out);
            $this->assertLessThan(1024, strlen($out), 'les octets de remplissage sont supprimés');
        }
        $this->assertLessThan(15, microtime(true) - $start, 'marge très large : le parcours octet par octet prenait plusieurs secondes');
    }

    public function test_millions_of_tiny_segments_are_rejected(): void
    {
        $jpeg = "\xFF\xD8".str_repeat($this->segment(0xE5, ''), 5000).$this->dqt().$this->sof(8, 8).$this->dht().self::SOS."\0\xFF\xD9";

        $this->expectException(\App\Support\ImageRejectedException::class);
        ImageMetadataStripper::strip($this->tmp($jpeg), sys_get_temp_dir().'/tiny.out');
    }

    public function test_many_stray_markers_after_the_scan_give_an_accurate_message(): void
    {
        $jpeg = $this->bareJpeg($this->dqt(), $this->sof(8, 8), $this->dht(), self::SOS, 'DATA'.str_repeat("\xFF\x01", 33000)."\xFF\xD9");

        try {
            ImageMetadataStripper::strip($this->tmp($jpeg), sys_get_temp_dir().'/stray.out');
            $this->fail('exception attendue');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('no valid SOS', $e->getMessage());
            $this->assertStringContainsString('after the scan data', $e->getMessage());
        }
    }

    public function test_png_orientation_helper_reads_the_exif_chunk(): void
    {
        $png = $this->pngWithChunks(40, 20, $this->chunk('eXIf', $this->exifTiff(6)));
        $this->assertSame(6, ImageMetadataStripper::pngOrientation($png));
        $this->assertNull(ImageMetadataStripper::pngOrientation($this->pngWithChunks(40, 20)));
        $this->assertNull(ImageMetadataStripper::pngOrientation('not a png'));
    }

    public function test_zero_dimensions_are_rejected_by_the_dimension_policy(): void
    {
        $this->assertNotNull(\App\Support\UploadedMedia::dimensionError(0, 100));
        $this->assertNotNull(\App\Support\UploadedMedia::dimensionError(100, 0));
        $this->assertNull(\App\Support\UploadedMedia::dimensionError(1, 1));

        $zero = "\x89PNG\r\n\x1a\n".$this->chunk('IHDR', pack('NNCCCCC', 0, 10, 8, 2, 0, 0, 0));
        $this->assertNotNull(\App\Support\UploadedMedia::decodeBlocker($zero));
    }
}
