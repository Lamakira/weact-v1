<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Candidature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Livrable validé exposé dans la bibliothèque d'assets Producteur (UGC 4.7) :
 * contexte owner (asymétrie FK booking↔candidature) + URLs média signées
 * (lecture inline ET téléchargement attachment). Calque de
 * DeliverableReviewResource MAIS orienté ARCHIVE (validated) : on retire les
 * champs review-only (review_note, submitted_at, review_due_at, deadline_at,
 * chrono_started_at) et on ajoute validated_at + download_url. La signature EST
 * la garde (D-4.4.c / D-4.7.c) : ces URLs ne sont mintées que dans la réponse
 * Producteur-scopée (ProducerUgcLibraryController::index).
 *
 * @mixin \App\Models\Deliverable
 */
class DeliverableAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->owner; // chargé via morphWith (controller)

        // Asymétrie : Booking.face = User (nom via userable=Face) ;
        // Candidature.face = Face (prenom/nom directs).
        $face = $owner instanceof Booking
            ? $owner->face?->userable
            : ($owner instanceof Candidature ? $owner->face : null);
        $faceName = $face !== null
            ? trim(($face->prenom ?? '').' '.($face->nom ?? ''))
            : null;

        $productName = $owner instanceof Booking
            ? $owner->nom_produit
            : ($owner instanceof Candidature ? $owner->mission?->nom_produit : null);

        $ttl = (int) config('ugc.video_url_ttl_minutes', 30);

        return [
            'id' => $this->uuid,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'validation_status' => $this->validation_status->value,
            'validation_status_label' => $this->validation_status->label(),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'duree_seconds' => $this->duree_seconds,
            'owner_type' => $owner instanceof Booking ? 'booking'
                : ($owner instanceof Candidature ? 'candidature' : null),
            'owner_id' => $owner?->uuid,
            'face_name' => $faceName !== '' ? $faceName : null,
            'product_name' => $productName,
            'video_url' => URL::temporarySignedRoute(
                'producer.deliverables.video',
                now()->addMinutes($ttl),
                ['deliverable' => $this->uuid],
            ),
            'thumbnail_url' => $this->thumbnail_path !== null
                ? URL::temporarySignedRoute(
                    'producer.deliverables.thumbnail',
                    now()->addMinutes($ttl),
                    ['deliverable' => $this->uuid],
                )
                : null,
            'download_url' => URL::temporarySignedRoute(
                'producer.deliverables.download',
                now()->addMinutes($ttl),
                ['deliverable' => $this->uuid],
            ),
        ];
    }
}
