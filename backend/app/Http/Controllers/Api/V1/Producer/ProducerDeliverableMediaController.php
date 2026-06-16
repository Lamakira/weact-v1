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

    /**
     * Téléchargement de la vidéo validée (bibliothèque d'assets, UGC 4.7) : sert
     * le video_path avec Content-Disposition: attachment + un nom de fichier
     * lisible (≠ video() qui sert inline pour la lecture <video>). Garde par
     * signature (route dans le groupe `signed`, D-4.7.c).
     */
    public function download(Deliverable $deliverable): BinaryFileResponse
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        $path = $deliverable->video_path;
        abort_unless($disk->exists($path), 404);

        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'mp4';
        $filename = 'ugc-'.$deliverable->kind->value.'-'.substr($deliverable->uuid, 0, 8).'.'.$ext;

        return response()->download($disk->path($path), $filename); // Content-Disposition: attachment
    }

    private function serve(string $path): BinaryFileResponse
    {
        $disk = Storage::disk((string) config('ugc.storage_disk', 'local'));
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path));
    }
}
