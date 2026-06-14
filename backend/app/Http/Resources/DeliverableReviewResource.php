<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Candidature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Livrable enrichi pour l'inbox de validation Producteur (5A, UGC 4.4) :
 * contexte owner (asymétrie FK booking↔candidature) + URLs média signées.
 * NE PAS confondre avec DeliverableResource (Face-facing, sans URL).
 *
 * @mixin \App\Models\Deliverable
 */
class DeliverableReviewResource extends JsonResource
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
            'review_note' => $this->review_note,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'chrono_started_at' => $this->chrono_started_at->toIso8601String(),
            'deadline_at' => $this->deadline_at->toIso8601String(),
            'review_due_at' => $this->submitted_at?->copy()
                ->addHours((int) config('ugc.deliverable_review_sla_hours', 48))->toIso8601String(),
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
        ];
    }
}
