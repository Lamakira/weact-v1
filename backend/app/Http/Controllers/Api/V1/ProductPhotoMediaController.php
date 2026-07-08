<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductPhoto;
use App\Support\ImageVariantGenerator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streaming des photos produit UGC d'un BOOKING (disque privé) — spec photos
 * produit. Accès via URL signée (calque ProducerDeliverableMediaController,
 * D-4.4.c) : la signature EST la garde, pas d'auth Sanctum (un <img src> ne
 * porte pas le header api.token). Les URLs ne sont mintées que par les
 * accessors de ProductPhoto, sérialisés dans des réponses booking déjà
 * scopées aux deux parties (Face destinataire + Producteur propriétaire).
 * Les photos de MISSION (disque public) ne passent jamais ici (asset direct).
 * Aucune mutation.
 */
class ProductPhotoMediaController extends Controller
{
    public function original(ProductPhoto $productPhoto): BinaryFileResponse
    {
        $layout = ImageVariantGenerator::layoutFor($productPhoto);

        return $this->serve($productPhoto, $layout['dir'].'/'.$productPhoto->filename);
    }

    public function grid(ProductPhoto $productPhoto): BinaryFileResponse
    {
        return $this->serveVariant($productPhoto, 'grid');
    }

    public function large(ProductPhoto $productPhoto): BinaryFileResponse
    {
        return $this->serveVariant($productPhoto, 'large');
    }

    private function serveVariant(ProductPhoto $productPhoto, string $variant): BinaryFileResponse
    {
        $layout = ImageVariantGenerator::layoutFor($productPhoto);
        $spec = $layout['variants'][$variant];
        $filename = $productPhoto->getAttribute($spec['column']);

        abort_if(! is_string($filename) || $filename === '', 404);

        return $this->serve($productPhoto, $spec['dir'].'/'.$filename);
    }

    private function serve(ProductPhoto $productPhoto, string $path): BinaryFileResponse
    {
        $disk = Storage::disk(ImageVariantGenerator::diskFor($productPhoto));
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }
}
