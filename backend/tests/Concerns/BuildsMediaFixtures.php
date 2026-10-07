<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait BuildsMediaFixtures
{
    /**
     * JPEG valide (GD) avec un segment APP1 EXIF : orientation + latitude GPS.
     */
    protected function jpegWithExif(int $width, int $height, int $orientation): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($im, 255, 0, 0));
        ob_start();
        imagejpeg($im, null, 90);
        $jpeg = (string) ob_get_clean();

        $ifd0 = pack('v', 2)
            .pack('vvVv2', 0x0112, 3, 1, $orientation, 0)
            .pack('vvVV', 0x8825, 4, 1, 38)
            .pack('V', 0);
        $gps = pack('v', 2)
            .pack('vvV', 0x0001, 2, 1)."N\0\0\0"
            .pack('vvVV', 0x0002, 5, 3, 68)
            .pack('V', 0);
        $rationals = pack('V6', 6, 1, 30, 1, 0, 1);
        $tiff = "II*\0".pack('V', 8).$ifd0.$gps.$rationals;
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    /**
     * mp4 réel (ffmpeg) portant un tag de localisation ; null si ffmpeg est absent.
     */
    protected function mp4WithLocation(string $path): bool
    {
        $bin = (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg');
        if (! is_executable($bin)) {
            return false;
        }

        exec(escapeshellarg($bin).' -y -v error -f lavfi -i testsrc=duration=1:size=64x64:rate=10 -metadata '
            .escapeshellarg('location=+06.3703+002.3912/').' '.escapeshellarg($path).' 2>&1', $out, $code);

        return $code === 0;
    }
}
