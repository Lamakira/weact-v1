<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
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
        // Internals de facturation Producer (commission plateforme) : jamais
        // exposés aux Faces (UGC 2.1 review) — surfaces Producer/Admin inchangées.
        $requesterIsFace = $request->user()?->userable instanceof Face;

        $hasPaidPayment = array_key_exists('has_paid_payment', $this->resource->getAttributes())
            ? (bool) $this->resource->getAttribute('has_paid_payment')
            : \App\Models\MissionPayment::where('mission_id', $this->id)->where('status', 'paid')->exists();
        $displayStatus = $this->resource->displayStatus($hasPaidPayment);

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
            'commission_ugc' => $this->when(! $requesterIsFace, $this->commission_ugc),
            'commission_paid_at' => $this->when(! $requesterIsFace, fn () => $this->commission_paid_at?->toIso8601String()),
            // Photos produit UGC (spec photos produit) — whenLoaded : la clé est OMISE
            // sur les listes qui ne chargent pas la relation, présente sur les détails
            // et sur la découverte UGC (vitrine des cartes, écran 6A).
            'product_photos' => ProductPhotoResource::collection($this->whenLoaded('productPhotos')),
            'is_accepting_candidatures' => $this->isAcceptingCandidatures(),
            // Listings pre-load the flag (Mission::scopeWithPaidPaymentFlag); the query is the fallback.
            'has_paid_payment' => $hasPaidPayment,
            // Lot C3 : statut affiché, dérivé de status + type + has_paid_payment (aucune requête de plus).
            'display_status' => $displayStatus->value,
            'display_status_label' => $displayStatus->label(),
            // Owner-only: mirrors the exact rules of POST /producer/missions/{mission}/complete.
            'can_complete' => $this->when(! $requesterIsFace, fn () => array_key_exists('can_complete', $this->resource->getAttributes())
                ? (bool) $this->resource->getAttribute('can_complete')
                : $this->resource->canBeCompleted()),
            // Lot B4 : places libérées (détail Producer uniquement, posé par MissionController::show).
            'freed_places_count' => $this->when(
                ! $requesterIsFace && array_key_exists('freed_places_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttribute('freed_places_count'),
            ),
            'can_replace_freed_place' => $this->when(
                ! $requesterIsFace && array_key_exists('can_replace_freed_place', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('can_replace_freed_place'),
            ),
            'freed_places_refund_at' => $this->when(
                ! $requesterIsFace && array_key_exists('freed_places_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttribute('freed_places_count') > 0
                    ? $this->resource->shootingDayStartsAt()?->toIso8601String()
                    : null,
            ),
            // Lot C1 : résumé du cycle de vie (détail Producer uniquement, posé par MissionController::show).
            'summary' => $this->when(
                ! $requesterIsFace && array_key_exists('summary', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('summary'),
            ),
            // Lot D8 : marques des cartes côté Face (posées par MissionEligibility::annotate, listes Face).
            'my_candidature' => $this->when(
                $requesterIsFace && array_key_exists('eligibility', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('my_candidature'),
            ),
            'my_candidature_label' => $this->when(
                $requesterIsFace && array_key_exists('eligibility', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('my_candidature_label'),
            ),
            'eligibility' => $this->when(
                $requesterIsFace && array_key_exists('eligibility', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('eligibility'),
            ),
            'candidatures_count' => $this->candidatures_count ?? ($this->whenLoaded('candidatures') ? $this->candidatures->count() : 0),
            'producer' => new ProducerResource($this->whenLoaded('producer')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
