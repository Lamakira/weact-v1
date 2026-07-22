<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Colonnes de la rotation par carrousel (faces:rotate-listing-ranks).
 *
 * `tier` + `tier_rank` matérialisent la file par palier calculée par la
 * reconstruction nocturne : c'est ce qui permet au tick de permuter les files
 * SANS relire les Faces ni les abonnements (aucun publiclyListable(), aucune
 * requête sur `faces` / `face_subscriptions`).
 *
 * `source` distingue la base nocturne des générations de tick. Deux lecteurs
 * en dépendent :
 *  - le chemin filtré du listing public, qui sert TOUJOURS la dernière
 *    génération `nightly` (on ne fait jamais tourner un résultat filtré) ;
 *  - le watchdog de fraîcheur, qui doit continuer de surveiller la
 *    reconstruction nocturne et non les ticks écrits toutes les 5 minutes.
 *
 * Les trois colonnes sont NULLABLES et sans valeur par défaut, MAIS les lignes
 * déjà en base sont reprises : toute génération antérieure au déploiement a été
 * écrite par la reconstruction nocturne (seul écrivain de la table jusqu'ici),
 * donc `source = 'nightly'`. Sans cette reprise, entre `migrate` et le premier
 * rebuild la dernière génération nocturne serait introuvable : le watchdog de
 * fraîcheur crierait « jamais construit » (Log::critical + FAILURE) toutes les
 * heures et le chemin filtré perdrait sa base.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Garde de ré-entrance : la reprise de données ci-dessous doit pouvoir
        // être rejouée sur une base où les colonnes existent déjà (déploiement
        // partiel, ou vérification).
        if (! Schema::hasColumn('face_listing_ranks', 'source')) {
            Schema::table('face_listing_ranks', function (Blueprint $table): void {
                // Palier de la Face au moment de la reconstruction (valeur de
                // FaceSubscriptionTier). Photographie, pas une jointure : le tick
                // n'a pas le droit de relire l'abonnement.
                $table->string('tier', 32)->nullable();
                // Position (1-based) de la Face dans la file de son palier, avant
                // entrelacement. Le tick décale cette file de
                // (places_du_palier × ticks_écoulés) puis ré-entrelace.
                $table->unsignedInteger('tier_rank')->nullable();
                // 'nightly' = base d'équité, 'tick' = permutation dérivée.
                $table->string('source', 16)->nullable();

                // Retrouver la dernière génération `nightly` est fait à CHAQUE
                // requête publique filtrée et à chaque tick : index dédié.
                $table->index(['source', 'generation']);
            });
        }

        // Reprise rétroactive. Valeur littérale et non FaceListingRotation::
        // SOURCE_NIGHTLY : une migration est un artefact historique, elle ne
        // doit pas dépendre d'une classe applicative qui peut être renommée.
        // `tier` / `tier_rank` restent NULL sur ces lignes — le tick refuse de
        // permuter une génération sans photographie de palier et attend le
        // prochain rebuild, ce qui est le comportement voulu.
        DB::table('face_listing_ranks')
            ->whereNull('source')
            ->update(['source' => 'nightly']);
    }

    public function down(): void
    {
        Schema::table('face_listing_ranks', function (Blueprint $table): void {
            $table->dropIndex(['source', 'generation']);
            $table->dropColumn(['tier', 'tier_rank', 'source']);
        });
    }
};
