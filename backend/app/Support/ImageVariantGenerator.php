<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Shared variant-generation engine used by both the queued job
 * (GenerateImageVariants) and the retrofit command (images:generate-variants).
 *
 * Only MISSING variants are produced (idempotent, resumable): a variant is
 * considered present when its DB column is filled AND the file exists on
 * disk. The original file is never re-encoded, moved or deleted.
 */
class ImageVariantGenerator
{
    public const DISK = 'public';

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
    public function generate(Face|Producer|FacePhoto $model, bool $dryRun = false): array
    {
        $config = $this->configFor($model);
        $disk = Storage::disk(self::DISK);

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
        // Decoded lazily so a fully up-to-date row never reads the original.
        $source = null;

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

            $source ??= $disk->get($config['dir'].'/'.$originalFilename);

            $image = Image::read($source);

            $encoded = match ($variant) {
                'thumbnail' => $image->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE)->toJpeg(self::QUALITY),
                'medium' => $image->scaleDown(width: self::MEDIUM_WIDTH)->toWebp(self::QUALITY),
                'grid' => $image->scaleDown(width: self::GRID_WIDTH)->toWebp(self::QUALITY),
                'large' => $image->scaleDown(width: self::LARGE_WIDTH)->toWebp(self::LARGE_QUALITY),
                default => throw new \LogicException("Unknown image variant [{$variant}]"),
            };

            $disk->put($spec['dir'].'/'.$filename, $encoded->toString());
            $writtenFiles[] = $spec['dir'].'/'.$filename;

            if (! $hasStoredFilename) {
                $updates[$spec['column']] = $filename;
            }
        }

        if (! $dryRun && $updates !== []) {
            // Optimistic guard: the row may have been deleted or re-uploaded
            // (new original) while this run was encoding. Only claim the
            // columns if the row still points to the original we read —
            // otherwise a slow run would overwrite the newer photo's columns
            // (or resurrect a deleted one).
            $affected = $model->newQuery()
                ->whereKey($model->getKey())
                ->where($config['original_column'], $originalFilename)
                ->update($updates);

            if ($affected === 0) {
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
     * Per-entity storage layout: original directory + one sibling directory
     * and one column per variant. Mirrors the paths used by the photo
     * services (upload/delete) — keep both in sync.
     *
     * @return array{dir: string, original_column: string, variants: array<string, array{column: string, dir: string}>}
     */
    private function configFor(Face|Producer|FacePhoto $model): array
    {
        return match (true) {
            $model instanceof Face => [
                'dir' => 'avatars/faces',
                'original_column' => 'profile_photo',
                'variants' => [
                    'thumbnail' => ['column' => 'profile_photo_thumbnail', 'dir' => 'avatars/faces/thumbnails'],
                    'medium' => ['column' => 'profile_photo_medium', 'dir' => 'avatars/faces/medium'],
                    'grid' => ['column' => 'profile_photo_grid', 'dir' => 'avatars/faces/grid'],
                    'large' => ['column' => 'profile_photo_large', 'dir' => 'avatars/faces/large'],
                ],
            ],
            $model instanceof Producer => [
                'dir' => 'avatars/producers',
                'original_column' => 'profile_photo',
                'variants' => [
                    'thumbnail' => ['column' => 'profile_photo_thumbnail', 'dir' => 'avatars/producers/thumbnails'],
                    'medium' => ['column' => 'profile_photo_medium', 'dir' => 'avatars/producers/medium'],
                    'grid' => ['column' => 'profile_photo_grid', 'dir' => 'avatars/producers/grid'],
                    'large' => ['column' => 'profile_photo_large', 'dir' => 'avatars/producers/large'],
                ],
            ],
            default => [
                'dir' => 'avatars/faces/albums',
                'original_column' => 'filename',
                'variants' => [
                    'thumbnail' => ['column' => 'thumbnail', 'dir' => 'avatars/faces/albums/thumbnails'],
                    'medium' => ['column' => 'medium', 'dir' => 'avatars/faces/albums/medium'],
                    'grid' => ['column' => 'grid', 'dir' => 'avatars/faces/albums/grid'],
                    'large' => ['column' => 'large', 'dir' => 'avatars/faces/albums/large'],
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
