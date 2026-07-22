<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FaceSubscriptionTier;
use App\Services\FaceEntitlementService;
use Illuminate\Support\Facades\DB;

/**
 * Vocabulaire partagé de la rotation du listing public.
 *
 * Trois choses vivent ici parce qu'elles sont lues par des acteurs qui ne se
 * connaissent pas — la reconstruction nocturne, la commande de tick, le
 * contrôleur public et le watchdog de fraîcheur — et qu'une divergence entre
 * eux serait silencieuse :
 *
 *  - `SOURCE_*` : la valeur écrite dans `face_listing_ranks.source`. Le
 *    contrôleur (chemin filtré) et le watchdog filtrent dessus.
 *  - `tierWeightsByPriority()` : les quotas WRR. Le tick DOIT ré-entrelacer
 *    avec EXACTEMENT les mêmes poids et le même ordre de priorité que la
 *    reconstruction nocturne, sinon la permutation ne serait plus une
 *    permutation de la base mais un autre classement.
 *  - `latestNightlyGeneration()` : « quelle génération est la base d'équité
 *    courante », question posée à l'identique par le tick et par le chemin
 *    filtré du listing.
 */
final class FaceListingRotation
{
    /**
     * Génération d'équité écrite par faces:rebuild-listing-ranks. Base des
     * ticks, seule génération surveillée par le watchdog de fraîcheur, seule
     * génération servie aux requêtes filtrées.
     */
    public const SOURCE_NIGHTLY = 'nightly';

    /**
     * Génération dérivée écrite par faces:rotate-listing-ranks : une simple
     * permutation des files de la base nocturne, sans aucun recalcul d'équité.
     */
    public const SOURCE_TICK = 'tick';

    /**
     * Verrou d'écriture UNIQUE de `face_listing_ranks`.
     *
     * La reconstruction nocturne et le tick calculent tous deux
     * MAX(generation)+1 puis insèrent : deux verrous de noms différents les
     * laissaient se croiser (le tick tourne toutes les 5 minutes donc tombe
     * sur 03:00 et peut chevaucher le rebuild de 03:15, ou un rebuild manuel),
     * et le perdant
     * mourait sur la contrainte unique (generation, face_id) — si c'était le
     * rebuild, l'équité de la nuit était perdue pour 24 h. Un seul écrivain de
     * la table à la fois : un seul nom de verrou.
     */
    public const WRITE_LOCK = 'face_listing_ranks:write';

    /**
     * TTL du verrou d'écriture, en secondes.
     *
     * Dimensionné sur le PLUS LONG des deux écrivains (la reconstruction
     * nocturne, qui parcourt toutes les Faces), jamais sur l'intervalle de
     * tick : un TTL égal à l'intervalle laisserait une transaction longue
     * expirer sous le process qui la tient et un second écrivain entrer.
     * Un process tué fait au pire sauter quelques ticks — la rotation est
     * idempotente par indice de tick, le tick suivant écrit la bonne
     * permutation.
     */
    public const WRITE_LOCK_TTL_SECONDS = 600;

    /**
     * Intervalle de planification de faces:rotate-listing-ranks, en minutes
     * (routes/console.php : everyFiveMinutes()). Un `tick_minutes` qui n'en est
     * pas un multiple fait sauter des indices de tick — d'où l'avertissement
     * émis par la commande de rotation.
     */
    public const SCHEDULE_INTERVAL_MINUTES = 5;

    /**
     * Durée d'un intervalle de rotation, en minutes, telle que la config la
     * définit. <= 0 = rotation désactivée (coupe-circuit).
     *
     * Une valeur non numérique vaut 0 (donc coupe-circuit) : elle est
     * DÉNONCÉE par RotateFaceListingRanksCommand::auditConfiguration(), pas
     * ici — ce getter est aussi appelé par le contrôleur public, à chaque
     * requête, et n'a pas le droit d'écrire dans les logs.
     */
    public static function tickMinutes(): int
    {
        $raw = config('face_listing_rotation.tick_minutes', self::SCHEDULE_INTERVAL_MINUTES);

        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * listing_quota par palier, classé par priorité DÉCROISSANTE (élite en
     * premier) — l'ordre des clés porte à la fois le tie-break du WRR lissé et
     * la priorité de redistribution des slots d'un palier vide.
     *
     * L'univers des paliers est l'enum FaceSubscriptionTier et chaque priorité
     * vient de l'accesseur central validé (la garde stricte `is_int` vit une
     * seule fois, dans FaceEntitlementService::buildCapabilities). Les quotas
     * sont lus dans le tableau de config déjà chargé — le service de
     * classement fail-loud sur une valeur manquante ou non entière.
     *
     * @return array<string, mixed> palier => listing_quota (validé par FaceListingRankingService)
     */
    public static function tierWeightsByPriority(FaceEntitlementService $entitlements): array
    {
        /** @var array<string, array<string, mixed>> $tiersConfig */
        $tiersConfig = config('face_subscription_tiers.tiers', []);

        $priorities = [];
        foreach (FaceSubscriptionTier::cases() as $tier) {
            $priorities[$tier->value] = $entitlements->capabilitiesForTier($tier)->sortPriority;
        }
        asort($priorities);

        $weights = [];
        foreach (array_keys($priorities) as $tierValue) {
            $weights[$tierValue] = ($tiersConfig[$tierValue]['capabilities'] ?? [])['listing_quota'] ?? null;
        }

        return $weights;
    }

    /**
     * Numéro de la dernière génération d'équité écrite, ou null tant qu'aucune
     * ligne n'est identifiable comme telle (table vide, ou rangs écrits avant
     * l'introduction de la colonne `source`).
     */
    public static function latestNightlyGeneration(): ?int
    {
        $generation = DB::table('face_listing_ranks')
            ->where('source', self::SOURCE_NIGHTLY)
            ->max('generation');

        return $generation === null ? null : (int) $generation;
    }
}
