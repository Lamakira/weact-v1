<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * Détection des métadonnées vidéo à nettoyer (le nettoyage lui-même est fait par
 * VideoMetadataStripper ; pour les images, par ImageMetadataStripper).
 */
final class MediaMetadataCleaner
{
    /** Tags de conteneur structurels qui subsistent après un remux propre. */
    private const STRUCTURAL_VIDEO_TAGS = ['major_brand', 'minor_version', 'compatible_brands', 'encoder'];

    /**
     * Vrai si le conteneur porte un tag autre que les tags structurels
     * (localisation, modèle d'appareil, dates…) OU un flux autre que vidéo/audio
     * (données, sous-titres, télémétrie : peuvent embarquer le GPS).
     *
     * @throws \RuntimeException Si ffprobe échoue
     */
    public static function videoHasMetadata(string $fullPath): bool
    {
        $result = Process::timeout(60)->run([
            (string) config('ffmpeg.ffprobe_binary', '/usr/bin/ffprobe'),
            '-v', 'error',
            '-show_entries', 'format_tags:stream=codec_type',
            '-of', 'json',
            $fullPath,
        ]);

        if (! $result->successful()) {
            throw new \RuntimeException('ffprobe failed: '.trim($result->errorOutput()));
        }

        $data = json_decode($result->output(), true);
        if (! is_array($data)) {
            return false;
        }

        $tags = isset($data['format']['tags']) && is_array($data['format']['tags'])
            ? array_keys($data['format']['tags'])
            : [];

        foreach ($tags as $tag) {
            if (! in_array(strtolower((string) $tag), self::STRUCTURAL_VIDEO_TAGS, true)) {
                return true;
            }
        }

        $streams = isset($data['streams']) && is_array($data['streams']) ? $data['streams'] : [];
        foreach ($streams as $stream) {
            $type = is_array($stream) ? ($stream['codec_type'] ?? null) : null;
            if ($type !== 'video' && $type !== 'audio') {
                return true;
            }
        }

        return false;
    }
}
