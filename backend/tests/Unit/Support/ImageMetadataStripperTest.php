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
        foreach (['tEXt', 'iTXt', 'zTXt', 'eXIf'] as $dropped) {
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
        $block = str_repeat('0123456789abcdef', 65536); // 1 Mo
        for ($i = 0; $i < 48; $i++) {
            fwrite($h, $block);
        }
        fwrite($h, "\xFF\xD9");
        fclose($h);
        unset($block);

        $out = $path.'.out';
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        ImageMetadataStripper::strip($path, $out);

        $delta = memory_get_peak_usage() - $before;
        $this->assertLessThan(4 * 1024 * 1024, $delta, "pic mémoire {$delta} octets sur un fichier de 48 Mo");
        $this->assertSame(filesize($path) - strlen($this->app1Exif(6)) + 36, filesize($out));
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

    private function motionPhoto(): string
    {
        $secondary = $this->withSegments($this->plainJpeg(8, 8), $this->app1Exif(3)); // image secondaire AVEC son Exif/GPS
        $mpf = $this->segment(0xE2, "MPF\0".str_repeat("\x01", 20));                  // APP2 MPF : pointe vers l'image secondaire
        $primary = $this->withSegments($this->plainJpeg(40, 20), $this->segment(0xE2, self::ICC), $mpf);

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
}
