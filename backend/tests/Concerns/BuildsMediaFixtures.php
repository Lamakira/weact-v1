<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait BuildsMediaFixtures
{
    /**
     * Payload TIFF/EXIF (little-endian) : Orientation + GPS (lat ref + lat) + Make.
     */
    protected function exifTiff(int $orientation): string
    {
        // IFD0 à l'offset 8 : 3 entrées => 2 + 36 + 4 = 42 => finit à 50.
        $gpsOffset = 50;
        $makeOffset = 50 + 30 + 24;   // après l'IFD GPS (30) et les rationnels (24)
        $ifd0 = pack('v', 3)
            .pack('vvVV', 0x010F, 2, 6, $makeOffset)    // Make "Canon\0" (hors-ligne)
            .pack('vvVv2', 0x0112, 3, 1, $orientation, 0)  // Orientation
            .pack('vvVV', 0x8825, 4, 1, $gpsOffset)        // GPSInfo
            .pack('V', 0);
        $gps = pack('v', 2)
            .pack('vvV', 0x0001, 2, 1)."N\0\0\0"
            .pack('vvVV', 0x0002, 5, 3, $gpsOffset + 30)
            .pack('V', 0);
        $rationals = pack('V6', 6, 1, 30, 1, 0, 1);

        return "II*\0".pack('V', 8).$ifd0.$gps.$rationals."Canon\0";
    }

    protected function app1Exif(int $orientation): string
    {
        $tiff = $this->exifTiff($orientation);

        return "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
    }

    protected function segment(int $marker, string $payload): string
    {
        return "\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload;
    }

    /**
     * JPEG valide (GD) sans métadonnées EXIF.
     */
    protected function plainJpeg(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, intdiv($width, 2), $height, (int) imagecolorallocate($im, 255, 0, 0));
        ob_start();
        imagejpeg($im, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * Insère des segments juste après le SOI.
     */
    protected function withSegments(string $jpeg, string ...$segments): string
    {
        return substr($jpeg, 0, 2).implode('', $segments).substr($jpeg, 2);
    }

    /**
     * JPEG valide (GD) avec un segment APP1 EXIF : orientation + GPS + appareil.
     */
    protected function jpegWithExif(int $width, int $height, int $orientation): string
    {
        return $this->withSegments($this->plainJpeg($width, $height), $this->app1Exif($orientation));
    }

    /**
     * mp4 réel (ffmpeg) portant un tag de localisation ; false si ffmpeg est absent.
     */
    protected function mp4WithLocation(string $path): bool
    {
        return $this->ffmpeg([
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x64:rate=10',
            '-metadata', 'location=+06.3703+002.3912/',
            $path,
        ]);
    }

    /**
     * mp4 avec piste audio, piste de sous-titres mov_text et tag de localisation global.
     */
    protected function mp4WithSubtitleTrack(string $path): bool
    {
        $srt = $path.'.srt';
        file_put_contents($srt, "1\n00:00:00,000 --> 00:00:00,900\nGPS 6.3703,2.3912\n");

        return $this->ffmpeg([
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x64:rate=10',
            '-f', 'lavfi', '-i', 'sine=duration=1',
            '-i', $srt,
            '-map', '0:v', '-map', '1:a', '-map', '2:s',
            '-c:v', 'libx264', '-c:a', 'aac', '-c:s', 'mov_text',
            '-metadata', 'location=+06.3703+002.3912/',
            $path,
        ]);
    }

    /**
     * mp4 dont le flux vidéo porte une display matrix de 90° (rotation) ; false si ffmpeg est absent.
     */
    protected function rotatedMp4(string $path): bool
    {
        $base = $path.'.base.mp4';
        if (! $this->ffmpeg(['-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x32:rate=10', '-metadata', 'location=+06.3703+002.3912/', $base])) {
            return false;
        }

        return $this->ffmpeg(['-display_rotation', '90', '-i', $base, '-c', 'copy', $path]);
    }

    /**
     * @return array{streams: list<array<string, mixed>>, format: array<string, mixed>}
     */
    protected function probe(string $path): array
    {
        $bin = (string) config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe');
        exec(escapeshellarg($bin).' -v error -show_entries format_tags:stream=codec_type,codec_name:stream_side_data=rotation -of json '.escapeshellarg($path), $out);
        $data = json_decode(implode("\n", $out), true);

        return is_array($data) ? $data : ['streams' => [], 'format' => []];
    }

    /**
     * @param  list<string>  $args  Arguments ffmpeg (entrées, options, sortie en dernier)
     */
    private function ffmpeg(array $args): bool
    {
        $bin = (string) config('ffmpeg.ffmpeg_binary', '/usr/bin/ffmpeg');
        if (! is_executable($bin)) {
            return false;
        }

        $cmd = escapeshellarg($bin).' -y -v error '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
        exec($cmd, $out, $code);

        return $code === 0;
    }
}
