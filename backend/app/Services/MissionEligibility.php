<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionTier;
use App\Enums\MissionGender;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Source unique de l'éligibilité d'une Face à une mission (lot D8) : utilisée par le
 * contrôle de candidature ET par les listes (marques des cartes), pour qu'il n'y ait
 * qu'une seule logique genre / abonnement.
 *
 * « Déjà candidaté » reste séparé (`my_candidature`) : ce n'est pas un critère d'éligibilité.
 */
class MissionEligibility
{
    public const OK = 'ok';

    public const GENDER_MISMATCH = 'gender_mismatch';

    public const SUBSCRIPTION_REQUIRED = 'subscription_required';

    public const UGC_SUBSCRIPTION_REQUIRED = 'ugc_subscription_required';

    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Premier blocage rencontré, dans l'ordre historique des contrôles de candidature :
     * abonnement UGC, abonnement standard, puis genre.
     */
    public function evaluate(Face $face, Mission $mission): string
    {
        if ($mission->type_mission === MissionType::Ugc) {
            if (! $this->entitlement->canAccessUgc($face)) {
                return self::UGC_SUBSCRIPTION_REQUIRED;
            }
        } elseif ($this->entitlement->capabilities($face)->tier === FaceSubscriptionTier::Free) {
            return self::SUBSCRIPTION_REQUIRED;
        }

        return $this->genderMismatch($face, $mission) ? self::GENDER_MISMATCH : self::OK;
    }

    /**
     * Genre incompatible : la mission cible un genre précis et la Face ne correspond pas
     * (ou n'a pas renseigné son genre).
     */
    public function genderMismatch(Face $face, Mission $mission): bool
    {
        if ($mission->genre_voulu === MissionGender::Tous) {
            return false;
        }

        return $face->sexe === null || $face->sexe->value !== $mission->genre_voulu->value;
    }

    /**
     * Pose `my_candidature` (statut ou null), son libellé et `eligibility` sur chaque mission de la
     * collection, avec UNE requête de candidatures pour tout le lot (pas de N+1).
     *
     * @param  Collection<int, Mission>  $missions
     */
    public function annotate(Face $face, Collection $missions): void
    {
        $statuses = $missions->isEmpty()
            ? collect()
            : Candidature::where('face_id', $face->id)
                ->whereIn('mission_id', $missions->pluck('id'))
                ->get(['mission_id', 'status', 'accepted_at'])
                ->keyBy('mission_id');

        foreach ($missions as $mission) {
            $candidature = $statuses->get($mission->id);
            $candidature?->setRelation('mission', $mission);
            $mission->setAttribute('my_candidature', $candidature?->status->value);
            // Libellé court, même source que « Mes candidatures » (displayStatusLabel).
            $mission->setAttribute('my_candidature_label', $candidature?->displayStatusLabel());
            $mission->setAttribute('eligibility', $this->evaluate($face, $mission));
        }
    }

    /**
     * Tri des listes : les missions au genre incompatible passent en fin de liste ;
     * l'ordre existant est conservé à l'intérieur de chaque groupe (à appeler AVANT
     * l'orderBy habituel, côté SQL pour que la pagination reste juste).
     *
     * @param  Builder<Mission>  $query
     * @return Builder<Mission>
     */
    public function orderGenderMismatchLast(Builder $query, Face $face): Builder
    {
        if ($face->sexe === null) {
            return $query->orderByRaw("CASE WHEN genre_voulu = 'tous' THEN 0 ELSE 1 END");
        }

        return $query->orderByRaw(
            "CASE WHEN genre_voulu = 'tous' OR genre_voulu = ? THEN 0 ELSE 1 END",
            [$face->sexe->value]
        );
    }
}
