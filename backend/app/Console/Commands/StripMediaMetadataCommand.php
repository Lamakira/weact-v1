<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceVideoType;
use App\Support\ImageMetadataStripper;
use App\Support\MediaMetadataCleaner;
use App\Support\VideoMetadataStripper;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Rétrofit : supprime les métadonnées (EXIF/GPS, atomes de localisation) des
 * médias DÉJÀ stockés. Originaux seulement (les variantes/miniatures sont des
 * ré-encodages GD/ffmpeg qui ne portent pas d'EXIF). Chemin et nom de fichier
 * inchangés (les rows DB les référencent) ; écriture temp + rename atomique ;
 * jamais de suppression d'un original en échec (warning + compteur, on continue).
 *
 * Dry run par défaut ; --apply écrit. Idempotent : un fichier propre est ignoré.
 */
class StripMediaMetadataCommand extends Command implements Isolatable
{
    /** Âge au-delà duquel un temporaire d'un run interrompu est supprimé. */
    private const STALE_TEMP_SECONDS = 3600;

    /**
     * @var string
     */
    protected $signature = 'media:strip-metadata
        {--apply : Nettoie réellement (sans cette option : dry run, rien n\'est modifié)}
        {--limit= : Nombre maximum de fichiers à nettoyer (ou à signaler en dry run), pour traiter par lots}
        {--path= : Ne traite que les dossiers sous ce préfixe (ex. avatars/faces)}';

    /**
     * @var string
     */
    protected $description = 'Supprime l\'EXIF/GPS des images et les métadonnées des vidéos déjà stockées (public + disque UGC privé). Dry run par défaut, --apply pour écrire. Idempotent.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) && (int) $limitOption > 0 ? (int) $limitOption : null;
        $pathOption = $this->option('path');
        $prefix = is_string($pathOption) && $pathOption !== '' ? trim($pathOption, '/') : null;

        $rows = [];
        $failures = [];
        $totals = ['scanned' => 0, 'with_metadata' => 0, 'cleaned' => 0, 'failed' => 0, 'unknown' => 0, 'stale_temp_removed' => 0];
        $remaining = $limit;

        foreach ($this->targets() as $target) {
            if ($prefix !== null && ! $this->prefixMatches($prefix, $target['dir'])) {
                continue;
            }
            if ($remaining !== null && $remaining <= 0) {
                break;
            }

            $stats = ['scanned' => 0, 'with_metadata' => 0, 'cleaned' => 0, 'failed' => 0, 'unknown' => 0, 'stale_temp_removed' => 0];
            $disk = Storage::disk($target['disk']);

            $files = $target['recursive'] ? $disk->allFiles($target['dir']) : $disk->files($target['dir']);
            sort($files);

            foreach ($files as $file) {
                if ($remaining !== null && $remaining <= 0) {
                    break;
                }
                if ($prefix !== null && ! str_starts_with($file, $prefix)) {
                    continue;
                }

                if ($this->isVariantPath($file)) {
                    continue;
                }

                $fullPath = $disk->path($file);

                // Temporaires d'un run interrompu : jamais traités comme médias ;
                // supprimés (avec --apply) s'ils ont plus d'une heure.
                if ($this->isTempFile($file)) {
                    if ($apply && $this->isStale($fullPath) && @unlink($fullPath)) {
                        $stats['stale_temp_removed']++;
                    }

                    continue;
                }

                // Sélection par octets magiques, jamais par l'extension (.jfif, .jpe, .m4v…).
                $recognised = $target['kind'] === 'image'
                    ? ImageMetadataStripper::format($fullPath) !== null
                    : VideoMetadataStripper::format($fullPath) !== null;

                if (! $recognised) {
                    $stats['unknown']++;
                    Log::info('media:strip-metadata skipped: unrecognised format', ['disk' => $target['disk'], 'path' => $file]);

                    continue;
                }

                $stats['scanned']++;

                try {
                    $dirty = $target['kind'] === 'image'
                        ? ImageMetadataStripper::isDirty($fullPath)
                        : MediaMetadataCleaner::videoHasMetadata($fullPath);
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $failures[] = $target['disk'].':'.$file.' — '.$e->getMessage();
                    Log::warning('media:strip-metadata inspection failed — file untouched', ['disk' => $target['disk'], 'path' => $file, 'error' => $e->getMessage()]);

                    continue;
                }

                if (! $dirty) {
                    continue;
                }

                $stats['with_metadata']++;

                if (! $apply) {
                    // Dry run : chaque fichier signalé consomme un slot de --limit.
                    if ($remaining !== null) {
                        $remaining--;
                    }

                    continue;
                }

                try {
                    if ($target['kind'] === 'image') {
                        ImageMetadataStripper::stripInPlace($fullPath);
                    } else {
                        VideoMetadataStripper::strip($fullPath);
                    }
                    $stats['cleaned']++;

                    // Seuls les nettoyages RÉUSSIS consomment un slot de --limit : un fichier
                    // en échec déterministe ne bloque pas le lot, on passe au suivant.
                    if ($remaining !== null) {
                        $remaining--;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $failures[] = $target['disk'].':'.$file.' — '.$e->getMessage();
                    Log::warning('media:strip-metadata cleaning failed — original kept', ['disk' => $target['disk'], 'path' => $file, 'error' => $e->getMessage()]);
                }
            }

            foreach ($stats as $key => $value) {
                $totals[$key] += $value;
            }

            $rows[] = [
                $target['disk'],
                $target['dir'],
                $stats['scanned'],
                $stats['with_metadata'],
                $apply ? $stats['cleaned'] : $stats['with_metadata'],
                $stats['failed'],
                $stats['unknown'],
            ];
        }

        $this->table(['disque', 'dossier', 'scannés', 'avec métadonnées', $apply ? 'nettoyés' : 'à nettoyer', 'échecs', 'ignorés (format inconnu)'], $rows);

        foreach (array_slice($failures, 0, 50) as $failure) {
            $this->warn('échec : '.$failure);
        }
        if (count($failures) > 50) {
            $this->warn(sprintf('… et %d autre(s) échec(s), voir les logs.', count($failures) - 50));
        }

        Log::info('media:strip-metadata terminé', ['apply' => $apply, 'limit' => $limit, 'path' => $prefix] + $totals);

        $this->info(sprintf(
            '%s : %d scanné(s), %d avec métadonnées, %d %s, %d échec(s), %d ignoré(s) (format inconnu), %d temporaire(s) périmé(s) supprimé(s).',
            $apply ? 'Nettoyage terminé' : 'Dry run terminé (rien modifié, relancer avec --apply)',
            $totals['scanned'],
            $totals['with_metadata'],
            $apply ? $totals['cleaned'] : $totals['with_metadata'],
            $apply ? 'nettoyé(s)' : 'à nettoyer',
            $totals['failed'],
            $totals['unknown'],
            $totals['stale_temp_removed'],
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Répertoires d'ORIGINAUX, issus des services d'upload. Images : non
     * récursif (les sous-dossiers thumbnails/medium/grid/large sont des variantes).
     *
     * @return list<array{disk: string, dir: string, kind: 'image'|'video', recursive: bool}>
     */
    private function targets(): array
    {
        $private = (string) config('ugc.storage_disk', 'local');
        $targets = [];

        $imageDirs = [
            'avatars/faces',          // ProfilePhotoService
            'avatars/producers',      // ProducerProfilePhotoService
            'avatars/faces/albums',   // PhotoAlbumService
            'logos/agencies',         // AgencyLogoService
            'articles/featured',      // ArticleService
            'products',               // ProductPhotoService (photos produit + réception)
        ];

        foreach (array_unique(['public', $private]) as $disk) {
            foreach ($imageDirs as $dir) {
                // avatars/articles/logos n'existent que sur public ; products sur les deux.
                if ($disk !== 'public' && $dir !== 'products') {
                    continue;
                }
                $targets[] = ['disk' => $disk, 'dir' => $dir, 'kind' => 'image', 'recursive' => false];
            }
        }

        foreach (FaceVideoType::cases() as $type) {
            $targets[] = ['disk' => 'public', 'dir' => 'videos/faces/'.$type->value, 'kind' => 'video', 'recursive' => false];
        }
        $targets[] = ['disk' => 'public', 'dir' => 'videos/faces/presentation', 'kind' => 'video', 'recursive' => false];
        $targets[] = ['disk' => $private, 'dir' => 'ugc/deliverables', 'kind' => 'video', 'recursive' => true];

        return $targets;
    }

    private function prefixMatches(string $prefix, string $dir): bool
    {
        return str_starts_with($dir, $prefix) || str_starts_with($prefix, $dir);
    }

    private function isTempFile(string $file): bool
    {
        return (bool) preg_match('#\.(stripped|stripping)\.[a-z0-9.]+$#i', $file);
    }

    private function isStale(string $fullPath): bool
    {
        $mtime = @filemtime($fullPath);

        return $mtime !== false && $mtime < time() - self::STALE_TEMP_SECONDS;
    }

    private function isVariantPath(string $file): bool
    {
        return (bool) preg_match('#/(thumbnails|medium|grid|large)/#', $file);
    }
}
