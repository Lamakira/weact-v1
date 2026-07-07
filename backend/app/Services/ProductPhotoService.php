<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateImageVariants;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProductPhoto;
use App\Models\Shipment;
use App\Support\ImageVariantGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stockage/nettoyage des photos produit UGC (spec photos produit) — partagé
 * entre BookingService (disque privé) et MissionService (disque public).
 *
 * attach() DOIT être appelé DANS la transaction de création de l'owner
 * (calque PhotoAlbumService::addPhoto) : les originaux sont écrits sur disque,
 * puis les rows créées ; sur throw, les fichiers déjà écrits sont supprimés
 * (une transaction DB ne rollback pas le filesystem) et l'exception remonte
 * pour rollback les rows ET la création de l'owner. Les jobs de variantes
 * sont afterCommit : jamais lancés pour une création rollbackée.
 */
class ProductPhotoService
{
    /**
     * @param  list<UploadedFile>  $photos
     * @param  string  $kind  'product' (Booking|Mission, à la création) | 'reception' (Shipment, à la réception)
     */
    public function attach(Booking|Mission|Shipment $owner, array $photos, string $disk, string $kind = 'product'): void
    {
        if ($photos === []) {
            return;
        }

        $storagePath = ImageVariantGenerator::layoutFor(new ProductPhoto)['dir'];
        $stored = [];

        try {
            foreach ($photos as $index => $photo) {
                $extension = $photo->getClientOriginalExtension() ?: 'jpg';
                $filename = Str::uuid()->toString().'.'.$extension;

                // Les disques `local`/`public` sont configurés `throw => false` :
                // putFileAs RETOURNE false sur échec d'écriture (disque plein,
                // permissions) au lieu de lever. Sans cette garde, on créerait une
                // row pointant un fichier absent (vignette cassée permanente, job
                // no-op). On lève pour déclencher le cleanup + rollback ci-dessous
                // (calque ImageVariantGenerator::generate).
                if (Storage::disk($disk)->putFileAs($storagePath, $photo, $filename) === false) {
                    throw new \RuntimeException("Failed to store product photo original [{$filename}] on disk [{$disk}].");
                }
                $stored[] = $filename;

                /** @var ProductPhoto $productPhoto */
                $productPhoto = $owner->productPhotos()->create([
                    'kind' => $kind,
                    'position' => $index + 1,
                    'disk' => $disk,
                    'filename' => $filename,
                ]);

                // afterCommit job: runs only if the enclosing transaction commits,
                // so it always sees the row (and never runs for a rolled-back one).
                dispatch(GenerateImageVariants::forModel($productPhoto));
            }
        } catch (\Throwable $e) {
            // Clean up the stored originals on failure (DB rollback does not
            // roll back filesystem writes) — calque PhotoAlbumService.
            foreach ($stored as $filename) {
                Storage::disk($disk)->delete($storagePath.'/'.$filename);
            }

            throw $e;
        }
    }

    /**
     * Efface les fichiers (original + variantes remplies, via le catalogue
     * partagé) ET les rows des photos produit d'un owner — appelé avant le
     * hard-delete d'une Mission (les colonnes morph n'ont pas de FK cascade).
     */
    public function detachAll(Booking|Mission $owner): void
    {
        $generator = app(ImageVariantGenerator::class);

        foreach ($owner->productPhotos()->get() as $photo) {
            $generator->deleteFiles($photo);
            $photo->delete();
        }
    }
}
