<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Deliverable;
use App\Models\ProductPhoto;
use App\Models\Shipment;
use App\Services\Ugc\UgcDeliverableService;
use App\Support\ImageVariantGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;

/**
 * Réconciliation du backlog de médias UGC orphelins. Les 3 tables enfants
 * polymorphes (product_photos / shipments / deliverables) n'ont pas de FK, donc
 * d'anciens hard-deletes — surtout MissionService::deleteMission, qui a fui les
 * shipments/livrables/photos de réception des candidatures depuis toujours — ont
 * laissé des rows + fichiers dont l'owner n'existe plus. Cette commande balaie
 * les 3 tables, cible les rows dont l'owner (morphTo) a disparu, supprime les
 * fichiers (product_photos : original + variantes disk-aware ; deliverables :
 * vidéo + miniature) et les rows, et laisse intactes celles dont l'owner existe.
 *
 * Ordre : shipments d'abord, puis product_photos, puis deliverables. Les photos
 * de réception vivent sur le Shipment (owner intermédiaire) : supprimer d'abord
 * les shipments orphelins rend leurs photos de réception directement orphelines,
 * balayées dans la même passe product_photos — d'où « un re-run ne trouve rien ».
 *
 * Idempotente/reprenable (calque PurgeExpiredMediaCommand +
 * GenerateImageVariantsCommand) : --dry-run compte sans rien supprimer ; un échec
 * par-row est loggé sans stopper le balayage et rend FAILURE en fin de course.
 */
class PurgeOrphanUgcMediaCommand extends Command implements Isolatable
{
    /**
     * @var string
     */
    protected $signature = 'ugc:purge-orphan-media {--dry-run : Compte les orphelins sans rien supprimer}';

    /**
     * @var string
     */
    protected $description = 'Purge les médias UGC orphelins (product_photos, shipments, deliverables dont l\'owner morph a disparu) — fichiers + rows. Idempotent, --dry-run, FAILURE si erreurs.';

    public function handle(ImageVariantGenerator $generator, UgcDeliverableService $deliverableService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totals = [
            'shipments' => 0,
            'product_photos' => 0,
            'deliverables' => 0,
            'errored' => 0,
        ];

        // 1) shipments orphelins : pas de fichier propre (juste la row). Purgés en
        //    premier pour que leurs photos de réception deviennent directement
        //    orphelines et soient balayées dans la passe product_photos ci-dessous.
        Shipment::query()->chunkById(200, function ($shipments) use ($dryRun, &$totals): void {
            foreach ($shipments as $shipment) {
                try {
                    if ($shipment->owner !== null) {
                        continue;
                    }
                    if ($dryRun) {
                        // Le run réel supprime ce shipment d'abord, puis ses photos
                        // de réception deviennent directement orphelines et sont
                        // comptées à la passe product_photos. En dry-run le shipment
                        // n'est pas supprimé, donc la passe 2 les sauterait (owner
                        // encore vivant) : on les compte ici pour que le prévisionnel
                        // ne sous-estime pas ce que le run effacera réellement.
                        $totals['product_photos'] += $shipment->productPhotos()->count();
                    } else {
                        $shipment->delete();
                    }
                    $totals['shipments']++;
                } catch (\Throwable $e) {
                    $totals['errored']++;
                    $this->logRowError('shipments', $shipment->id, $e);
                }
            }
        });

        // 2) product_photos orphelines (Booking/Mission/Shipment disparu) :
        //    fichiers (original + variantes, disk-aware via la colonne `disk`) + row.
        ProductPhoto::query()->chunkById(200, function ($photos) use ($generator, $dryRun, &$totals): void {
            foreach ($photos as $photo) {
                try {
                    if ($photo->owner !== null) {
                        continue;
                    }
                    if (! $dryRun) {
                        $generator->deleteFiles($photo);
                        $photo->delete();
                    }
                    $totals['product_photos']++;
                } catch (\Throwable $e) {
                    $totals['errored']++;
                    $this->logRowError('product_photos', $photo->id, $e);
                }
            }
        });

        // 3) deliverables orphelins (Booking/Candidature disparu) : vidéo +
        //    miniature (disque UGC privé) + row.
        Deliverable::query()->chunkById(200, function ($deliverables) use ($deliverableService, $dryRun, &$totals): void {
            foreach ($deliverables as $deliverable) {
                try {
                    if ($deliverable->owner !== null) {
                        continue;
                    }
                    if (! $dryRun) {
                        $deliverableService->deleteFiles($deliverable);
                        $deliverable->delete();
                    }
                    $totals['deliverables']++;
                } catch (\Throwable $e) {
                    $totals['errored']++;
                    $this->logRowError('deliverables', $deliverable->id, $e);
                }
            }
        });

        Log::info('ugc:purge-orphan-media terminé', ['dry_run' => $dryRun] + $totals);

        $this->info(sprintf(
            '%s : %d shipment(s), %d product_photo(s), %d deliverable(s) orphelin(s), %d erreur(s).',
            $dryRun ? 'Dry-run terminé' : 'Purge terminée',
            $totals['shipments'],
            $totals['product_photos'],
            $totals['deliverables'],
            $totals['errored'],
        ));

        // Même convention que PurgeExpiredMediaCommand/GenerateImageVariantsCommand :
        // remonte les échecs par-row au caller tout en restant idempotent/reprenable.
        return $totals['errored'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function logRowError(string $table, int $id, \Throwable $e): void
    {
        $this->error("Échec sur {$table} #{$id} : {$e->getMessage()}");
        Log::warning('ugc:purge-orphan-media: échec sur une ligne, balayage poursuivi', [
            'table' => $table,
            'id' => $id,
            'exception' => $e::class,
            'exception_message' => $e->getMessage(),
        ]);
    }
}
