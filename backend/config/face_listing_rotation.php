<?php

declare(strict_types=1);

/*
 * Rotation par carrousel du listing public des Faces.
 *
 * La reconstruction nocturne (faces:rebuild-listing-ranks) garde tout le
 * calcul d'équité (LRU page 1, mises en avant, nouveaux arrivants) et écrit
 * une génération `nightly`. La commande de tick (faces:rotate-listing-ranks)
 * ne fait que permuter les files de cette base pour que la page 1 se
 * renouvelle en cours de journée.
 *
 * Les deux réglages sont pilotables par variable d'environnement : couper la
 * rotation en production (incident, doute sur la charge) doit être un
 * `FACE_LISTING_TICK_MINUTES=0` + `php artisan config:clear`, JAMAIS un
 * redéploiement.
 */

return [
    /*
     * Durée d'un intervalle de rotation, en minutes. La page 1 change au plus
     * une fois par intervalle : l'indice de tick T = nombre d'intervalles
     * complets écoulés depuis la naissance de la génération nocturne, ce qui
     * rend la commande idempotente (deux exécutions dans le même intervalle
     * n'écrivent qu'une génération).
     *
     * 0 (ou négatif) DÉSACTIVE la rotation : la commande sort en SUCCESS sans
     * rien écrire ET la page publique retombe exactement sur l'ordre nocturne
     * (le contrôleur lit ce même réglage — sans quoi la dernière permutation
     * de tick resterait servie indéfiniment).
     *
     * Valeur laissée BRUTE (pas de cast ici) : le cast vit dans
     * FaceListingRotation::tickMinutes(), et la commande de rotation peut
     * ainsi dénoncer une valeur non numérique — qui vaudrait 0 après cast,
     * c'est-à-dire un carrousel coupé sans la moindre trace.
     */
    'tick_minutes' => env('FACE_LISTING_TICK_MINUTES', 5),

    /*
     * Nombre de générations conservées en base par la purge de rotation, la
     * courante COMPRISE. Elles ne servent qu'à l'épinglage de pagination : un
     * visiteur qui a reçu la génération G en page 1 doit encore pouvoir
     * demander sa page 2 quelques minutes plus tard. 12 = la génération
     * courante + 11 historiques, soit 55 min d'épinglage possible à raison
     * d'un tick toutes les 5 min. La base nocturne, elle, n'est JAMAIS purgée
     * par la rotation, quel que soit ce réglage.
     */
    'retained_ticks' => (int) env('FACE_LISTING_RETAINED_TICKS', 12),
];
