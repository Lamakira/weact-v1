<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Models\ProductPhoto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Shared variant-generation engine used by both the queued job
 * (GenerateImageVariants) and the retrofit command (images:generate-variants).
 *
 * configFor() is the single source of truth for each entity's storage layout
 * (original dir + one column/dir/encode-spec per variant). Generation
 * (generate()) AND file cleanup (deleteFiles(), used by the photo services)
 * both read it, so a dir/column/spec change happens in exactly one place.
 *
 * Only MISSING variants are produced (idempotent, resumable): a variant is
 * considered present when its DB column is filled AND the file exists on
 * disk. The original file is never re-encoded, moved or deleted by generate().
 */
class ImageVariantGenerator
{
    public const DISK = 'public';

    /**
     * Disque de stockage par-modèle : les entités historiques (avatars, album)
     * restent sur `public` ; ProductPhoto porte SA colonne `disk` (posée à la
     * création — `public` pour une Mission, disque UGC privé pour un Booking).
     * generate()/deleteFiles() lisent ce résolveur, jamais la constante seule.
     */
    public static function diskFor(Face|Producer|FacePhoto|ProductPhoto $model): string
    {
        return $model instanceof ProductPhoto ? $model->disk : self::DISK;
    }

    private const THUMBNAIL_SIZE = 150;

    private const MEDIUM_WIDTH = 800;

    private const GRID_WIDTH = 400;

    private const LARGE_WIDTH = 1600;

    private const QUALITY = 85;

    private const LARGE_QUALITY = 90;

    /**
     * Generate the missing variants for a model's original photo and fill the
     * matching columns. With $dryRun, only report what WOULD be generated.
     *
     * @return array{generated: list<string>, skipped: list<string>, missing_source: bool}
     */
    public function generate(Face|Producer|FacePhoto|ProductPhoto $model, bool $dryRun = false): array
    {
        $config = self::layoutFor($model);
        $disk = Storage::disk(self::diskFor($model));

        $originalFilename = $model->getAttribute($config['original_column']);

        if (! is_string($originalFilename)
            || $originalFilename === ''
            || ! $disk->exists($config['dir'].'/'.$originalFilename)) {
            return ['generated' => [], 'skipped' => [], 'missing_source' => true];
        }

        $generated = [];
        $skipped = [];
        $updates = [];
        $writtenFiles = [];
        // Decoded lazily and ONCE: a fully up-to-date row never reads the
        // original, and each variant transforms a fresh clone rather than
        // re-decoding the source raster from scratch.
        $decoded = null;

        foreach ($config['variants'] as $variant => $spec) {
            $storedFilename = $model->getAttribute($spec['column']);
            $hasStoredFilename = is_string($storedFilename) && $storedFilename !== '';
            $filename = $hasStoredFilename
                ? $storedFilename
                : $this->variantFilename($variant, $originalFilename);

            if ($hasStoredFilename && $disk->exists($spec['dir'].'/'.$filename)) {
                $skipped[] = $variant;

                continue;
            }

            $generated[] = $variant;

            if ($dryRun) {
                continue;
            }

            if ($decoded === null) {
                $source = $disk->get($config['dir'].'/'.$originalFilename);

                if ($source === null) {
                    // The original vanished between the top-of-method exists()
                    // pre-check and now (concurrent delete/re-upload). Treat it
                    // as a benign source-changed race — a skip, matching the top
                    // guard — not a corrupt-source failure; drop what we wrote.
                    foreach ($writtenFiles as $path) {
                        $disk->delete($path);
                    }

                    return ['generated' => [], 'skipped' => $skipped, 'missing_source' => true];
                }

                $decoded = Image::read($source);
            }

            // cover()/scaleDown() mutate the image in place, so every variant
            // must transform a fresh clone of the pristine decode — never the
            // cumulatively shrunk result of the previous iteration.
            $bytes = $this->encode(clone $decoded, $spec);

            $path = $spec['dir'].'/'.$filename;

            // The 'public' disk is configured 'throw' => false, so a failed
            // write (disk full, bad permissions) RETURNS false instead of
            // throwing. Never claim the column for a file that isn't on disk:
            // that would serve a 404 AND defeat the accessor's original
            // fallback. Discard what this run wrote and raise so the queued job
            // is a logged no-op (original still renders) and the retrofit counts
            // a failed row (exit FAILURE) instead of reporting a false-clean run.
            if ($disk->put($path, $bytes) === false) {
                foreach ($writtenFiles as $written) {
                    $disk->delete($written);
                }

                throw new \RuntimeException("Failed to write image variant [{$variant}] to [{$path}]");
            }

            $writtenFiles[] = $path;

            if (! $hasStoredFilename) {
                $updates[$spec['column']] = $filename;
            }
        }

        if (! $dryRun && $writtenFiles !== []) {
            // Optimistic guard: the row may have been deleted or re-uploaded
            // (new original) while this run was encoding. Only keep the files
            // and columns if the row still points to the original we read —
            // otherwise a slow run would overwrite the newer photo's columns,
            // resurrect a deleted one, or leave restored files orphaned.
            $guard = $model->newQuery()
                ->whereKey($model->getKey())
                ->where($config['original_column'], $originalFilename);

            // With columns to claim, the guarded UPDATE both claims them and
            // reports (affected rows) whether the row still matched. On a
            // pure-restore run (columns already set, only the files were
            // missing) there is nothing to claim, so a plain existence check
            // stands in — without it the guard would be skipped and files
            // written for a concurrently deleted/changed row would be orphaned.
            $stillCurrent = $updates !== []
                ? $guard->update($updates) > 0
                : $guard->exists();

            if (! $stillCurrent) {
                // Discard ONLY the files this run wrote — pre-existing
                // variants belong to the row's current state, not to us.
                foreach ($writtenFiles as $path) {
                    $disk->delete($path);
                }

                Log::info('image variants discarded: source changed during generation', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'original' => $originalFilename,
                ]);

                return ['generated' => [], 'skipped' => $skipped, 'missing_source' => false];
            }
        }

        return ['generated' => $generated, 'skipped' => $skipped, 'missing_source' => false];
    }

    /**
     * Delete a model's original file AND every filled variant file, from the
     * shared catalog. Idempotent (a missing file is skipped). Does NOT touch DB
     * columns — the caller owns the row lifecycle (null the columns, or delete
     * the row). Returns true if at least one referenced file existed on disk.
     *
     * The single delete counterpart to generate(): both derive their paths from
     * configFor(), so the three photo services never re-hardcode the layout.
     */
    public function deleteFiles(Face|Producer|FacePhoto|ProductPhoto $model): bool
    {
        $disk = Storage::disk(self::diskFor($model));
        $deletedAny = false;

        foreach ($this->referencedFiles($model) as $file) {
            if ($disk->exists($file['path'])) {
                $disk->delete($file['path']);
                $deletedAny = true;
            }
        }

        return $deletedAny;
    }

    /**
     * The disk paths a model currently references — the original plus every
     * filled variant — each tagged with its variant name (null for the
     * original), drawn from the shared catalog. Callers that need bespoke
     * per-path handling (e.g. the retention purge command's per-asset warning
     * logs) drive their own deletion from the same source of truth as
     * generate() and deleteFiles().
     *
     * @return list<array{path: string, variant: string|null}>
     */
    public function referencedFiles(Face|Producer|FacePhoto|ProductPhoto $model): array
    {
        $config = self::layoutFor($model);

        $original = $model->getAttribute($config['original_column']);
        $files = is_string($original) && $original !== ''
            ? [['path' => $config['dir'].'/'.$original, 'variant' => null]]
            : [];

        foreach ($config['variants'] as $variant => $spec) {
            $value = $model->getAttribute($spec['column']);
            if (is_string($value) && $value !== '') {
                $files[] = ['path' => $spec['dir'].'/'.$value, 'variant' => $variant];
            }
        }

        return $files;
    }

    /**
     * Resize + encode one variant from a (cloned) decoded image, per its spec.
     * A 'height' means a square cover crop (thumbnail); otherwise a
     * width-constrained scaleDown that never upscales.
     *
     * @param  array{column: string, dir: string, width: int, height?: int, format: string, quality: int}  $spec
     */
    private function encode(ImageInterface $image, array $spec): string
    {
        $resized = isset($spec['height'])
            ? $image->cover($spec['width'], $spec['height'])
            : $image->scaleDown(width: $spec['width']);

        $encoded = $spec['format'] === 'jpeg'
            ? $resized->toJpeg($spec['quality'])
            : $resized->toWebp($spec['quality']);

        return $encoded->toString();
    }

    /**
     * Per-entity storage layout: original directory + one sibling directory,
     * column and encode spec per variant. This is the single source of truth
     * consumed by generate()/deleteFiles() here (write/cleanup) AND by the
     * HasImageVariantUrls trait on the models (URL building) — adding or
     * renaming a variant is a one-place edit here. Public + static so the trait
     * reads it without a container resolution or a Model→Support instance
     * dependency.
     *
     * @return array{dir: string, original_column: string, variants: array<string, array{column: string, dir: string, width: int, height?: int, format: string, quality: int}>}
     */
    public static function layoutFor(Face|Producer|FacePhoto|ProductPhoto $model): array
    {
        return match (true) {
            // Photos produit UGC : grid 400 + large 1600 WebP SEULEMENT (vignettes
            // détail + lightbox, pas des avatars — ni thumbnail 150 ni medium 800).
            // Même layout relatif sur les deux disques (diskFor résout `public`
            // vs disque UGC privé selon la row).
            $model instanceof ProductPhoto => [
                'dir' => 'products',
                'original_column' => 'filename',
                'variants' => [
                    'grid' => ['column' => 'grid', 'dir' => 'products/grid', 'width' => self::GRID_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'large' => ['column' => 'large', 'dir' => 'products/large', 'width' => self::LARGE_WIDTH, 'format' => 'webp', 'quality' => self::LARGE_QUALITY],
                ],
            ],
            $model instanceof Face => [
                'dir' => 'avatars/faces',
                'original_column' => 'profile_photo',
                'variants' => [
                    'thumbnail' => ['column' => 'profile_photo_thumbnail', 'dir' => 'avatars/faces/thumbnails', 'width' => self::THUMBNAIL_SIZE, 'height' => self::THUMBNAIL_SIZE, 'format' => 'jpeg', 'quality' => self::QUALITY],
                    'medium' => ['column' => 'profile_photo_medium', 'dir' => 'avatars/faces/medium', 'width' => self::MEDIUM_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'grid' => ['column' => 'profile_photo_grid', 'dir' => 'avatars/faces/grid', 'width' => self::GRID_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'large' => ['column' => 'profile_photo_large', 'dir' => 'avatars/faces/large', 'width' => self::LARGE_WIDTH, 'format' => 'webp', 'quality' => self::LARGE_QUALITY],
                ],
            ],
            $model instanceof Producer => [
                'dir' => 'avatars/producers',
                'original_column' => 'profile_photo',
                'variants' => [
                    'thumbnail' => ['column' => 'profile_photo_thumbnail', 'dir' => 'avatars/producers/thumbnails', 'width' => self::THUMBNAIL_SIZE, 'height' => self::THUMBNAIL_SIZE, 'format' => 'jpeg', 'quality' => self::QUALITY],
                    'medium' => ['column' => 'profile_photo_medium', 'dir' => 'avatars/producers/medium', 'width' => self::MEDIUM_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'grid' => ['column' => 'profile_photo_grid', 'dir' => 'avatars/producers/grid', 'width' => self::GRID_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'large' => ['column' => 'profile_photo_large', 'dir' => 'avatars/producers/large', 'width' => self::LARGE_WIDTH, 'format' => 'webp', 'quality' => self::LARGE_QUALITY],
                ],
            ],
            default => [
                'dir' => 'avatars/faces/albums',
                'original_column' => 'filename',
                'variants' => [
                    'thumbnail' => ['column' => 'thumbnail', 'dir' => 'avatars/faces/albums/thumbnails', 'width' => self::THUMBNAIL_SIZE, 'height' => self::THUMBNAIL_SIZE, 'format' => 'jpeg', 'quality' => self::QUALITY],
                    'medium' => ['column' => 'medium', 'dir' => 'avatars/faces/albums/medium', 'width' => self::MEDIUM_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'grid' => ['column' => 'grid', 'dir' => 'avatars/faces/albums/grid', 'width' => self::GRID_WIDTH, 'format' => 'webp', 'quality' => self::QUALITY],
                    'large' => ['column' => 'large', 'dir' => 'avatars/faces/albums/large', 'width' => self::LARGE_WIDTH, 'format' => 'webp', 'quality' => self::LARGE_QUALITY],
                ],
            ],
        };
    }

    /**
     * Thumbnail keeps the original filename (JPEG bytes under the original
     * extension — historical convention); WebP variants share the UUID
     * basename with a .webp extension.
     */
    private function variantFilename(string $variant, string $originalFilename): string
    {
        return $variant === 'thumbnail'
            ? $originalFilename
            : pathinfo($originalFilename, PATHINFO_FILENAME).'.webp';
    }
}
