<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Mission
 */
class MissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'description' => $this->description,
            'date_tournage' => $this->date_tournage?->toIso8601String(),
            'profil_recherche' => $this->profil_recherche,
            'budget' => $this->budget,
            'date_limite_candidature' => $this->date_limite_candidature?->toIso8601String(),
            'nombre_faces_voulu' => $this->nombre_faces_voulu,
            'type_mission' => $this->type_mission?->value,
            'type_mission_label' => $this->type_mission?->label(),
            'type_mission_autre' => $this->type_mission_autre,
            'genre_voulu' => $this->genre_voulu?->value,
            'genre_voulu_label' => $this->genre_voulu?->label(),
            'lieu' => $this->lieu,
            'duree' => $this->duree,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Champs UGC (null pour les missions non-UGC)
            'type_compensation' => $this->type_compensation?->value,
            'type_compensation_label' => $this->type_compensation?->label(),
            'nom_produit' => $this->nom_produit,
            'valeur_produit' => $this->valeur_produit,
            'nombre_videos' => $this->nombre_videos,
            'montant_remuneration' => $this->montant_remuneration,
            'commission_ugc' => $this->commission_ugc,
            'is_accepting_candidatures' => $this->isAcceptingCandidatures(),
            'has_paid_payment' => \App\Models\MissionPayment::where('mission_id', $this->id)->where('status', 'paid')->exists(),
            'candidatures_count' => $this->candidatures_count ?? ($this->whenLoaded('candidatures') ? $this->candidatures->count() : 0),
            'producer' => new ProducerResource($this->whenLoaded('producer')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
