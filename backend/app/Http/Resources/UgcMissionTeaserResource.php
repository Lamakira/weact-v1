<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teaser paywall d'une mission UGC pour une Face non éligible (FR5).
 * Strictement les champs « carte verrouillée » de l'écran 6A — ne JAMAIS
 * ajouter description/profil_recherche/montant_remuneration/producer ici.
 *
 * @mixin \App\Models\Mission
 */
class UgcMissionTeaserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'titre' => $this->titre,
            'type_compensation' => $this->type_compensation?->value,
            'type_compensation_label' => $this->type_compensation?->label(),
            'nom_produit' => $this->nom_produit,
            'valeur_produit' => $this->valeur_produit,
            'nombre_videos' => $this->nombre_videos,
            'lieu' => $this->lieu,
            'date_limite_candidature' => $this->date_limite_candidature?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
