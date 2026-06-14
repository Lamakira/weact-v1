<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DeliverableValidationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Métadonnées d'un livrable vidéo UGC — JAMAIS le video_path brut ni d'URL
 * signée (D-4.1.e : la lecture vidéo Producteur sur disque privé = 4.4). 4.3
 * ajoute review_note / validated_at / review_due_at (SLA Producteur 48 h dérivé
 * serveur, uniquement quand in_review) — support de l'écran de validation 4.4.
 *
 * @mixin \App\Models\Deliverable
 */
class DeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'validation_status' => $this->validation_status->value,
            'validation_status_label' => $this->validation_status->label(),
            'review_note' => $this->review_note,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'chrono_started_at' => $this->chrono_started_at->toIso8601String(),
            'deadline_at' => $this->deadline_at->toIso8601String(),
            // SLA review Producteur (D-4.3.g) : submitted_at + 48 h, dérivé serveur,
            // exposé UNIQUEMENT tant que le livrable est in_review (sinon null).
            'review_due_at' => $this->validation_status === DeliverableValidationStatus::InReview && $this->submitted_at !== null
                ? $this->submitted_at->copy()->addHours((int) config('ugc.deliverable_review_sla_hours', 48))->toIso8601String()
                : null,
            'duree_seconds' => $this->duree_seconds,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
