<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streaming des médias livrables (disque privé) pour l'écran de validation
 * Producteur (5A, UGC 4.4). Accès via URL signée (D-4.4.c) : la signature EST
 * la garde (un <video src> ne peut pas porter api.token). response()->file()
 * gère les Range requests (seek). Aucune mutation.
 */
class ProducerDeliverableMediaController extends Controller
{
    public function video(Deliverable $deliverable): BinaryFileResponse
    {
        return $this->serve($deliverable->video_path);
    }

    public function thumbnail(Deliverable $deliverable): BinaryFileResponse
    {
        abort_if($deliverable->thumbnail_path === null, 404);

        return $this->serve($deliverable->thumbnail_path);
    }

    private function serve(string $path): BinaryFileResponse
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }
}
