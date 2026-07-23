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
 * `product_photos` est la SEULE extension consentie depuis D-2.1.c (décision PO
 * 2026-07-23) : la photo produit est l'argument d'upsell de la carte verrouillée.
 * Aucune fuite — ces rows sont sur le disque public (vitrine mission), leurs URLs
 * ne sont ni signées ni sensibles.
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
            // whenLoaded : la clé n'apparaît que si l'appelant a chargé la relation
            // (la découverte UGC le fait pour les deux branches).
            'product_photos' => ProductPhotoResource::collection($this->whenLoaded('productPhotos')),
            'lieu' => $this->lieu,
            'date_limite_candidature' => $this->date_limite_candidature?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
