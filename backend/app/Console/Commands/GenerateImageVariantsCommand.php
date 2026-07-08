<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Support\ImageVariantGenerator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class GenerateImageVariantsCommand extends Command implements Isolatable
{
    /**
     * @var string
     */
    protected $signature = 'images:generate-variants {--dry-run : Compte ce qui serait généré sans rien écrire}';

    /**
     * @var string
     */
    protected $description = 'Rétrofit des variantes d\'images manquantes (150/800/400/1600) pour les photos de profil Face/Producer et les albums — idempotent, reprenable';

    public function handle(ImageVariantGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totals = [
            'generated_variants' => 0,
            'skipped_variants' => 0,
            'missing_sources' => 0,
            'failed_rows' => 0,
        ];

        // Generation runs INLINE (not via queued jobs): mass-dispatching 3000+
        // jobs would drown the shared database queue used by payment webhooks.
        // Select only the columns generate() reads (key + original + variants)
        // rather than hydrating every wide row over the whole-table retrofit.
        $profileColumns = ['id', 'profile_photo', 'profile_photo_thumbnail', 'profile_photo_medium', 'profile_photo_grid', 'profile_photo_large'];

        $this->processEntity('faces', Face::query()->select($profileColumns)->whereNotNull('profile_photo'), $generator, $dryRun, $totals);
        $this->processEntity('producers', Producer::query()->select($profileColumns)->whereNotNull('profile_photo'), $generator, $dryRun, $totals);
        $this->processEntity('face_photos', FacePhoto::query()->select(['id', 'filename', 'thumbnail', 'medium', 'grid', 'large'])->whereNotNull('filename'), $generator, $dryRun, $totals);

        Log::info('images:generate-variants terminé', ['dry_run' => $dryRun] + $totals);

        $this->info(sprintf(
            '%s : %d variante(s) générée(s), %d déjà présente(s) (skipped), %d source(s) manquante(s), %d ligne(s) en échec.',
            $dryRun ? 'Dry-run terminé' : 'Rétrofit terminé',
            $totals['generated_variants'],
            $totals['skipped_variants'],
            $totals['missing_sources'],
            $totals['failed_rows'],
        ));

        // Same convention as PurgeExpiredMediaCommand: surface per-row
        // failures to the caller while staying idempotent and resumable.
        return $totals['failed_rows'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Builder<Face>|Builder<Producer>|Builder<FacePhoto>  $query
     * @param  array{generated_variants: int, skipped_variants: int, missing_sources: int, failed_rows: int}  $totals
     */
    private function processEntity(string $label, Builder $query, ImageVariantGenerator $generator, bool $dryRun, array &$totals): void
    {
        $entityTotals = ['generated_variants' => 0, 'skipped_variants' => 0, 'missing_sources' => 0, 'failed_rows' => 0];

        $query->chunkById(100, function ($models) use ($label, $generator, $dryRun, &$entityTotals): void {
            /** @var Face|Producer|FacePhoto $model */
            foreach ($models as $model) {
                try {
                    $result = $generator->generate($model, $dryRun);
                } catch (\Throwable $e) {
                    // Corrupt source, encoder failure… — log and keep going so
                    // the retrofit stays resumable over ~3000 rows.
                    $entityTotals['failed_rows']++;
                    Log::warning('images:generate-variants: échec sur une ligne, rétrofit poursuivi', [
                        'entity' => $label,
                        'id' => $model->getKey(),
                        'exception' => $e::class,
                        'exception_message' => $e->getMessage(),
                    ]);

                    continue;
                }

                if ($result['missing_source']) {
                    $entityTotals['missing_sources']++;
                    Log::info('images:generate-variants: fichier original absent, ligne skippée', [
                        'entity' => $label,
                        'id' => $model->getKey(),
                    ]);

                    continue;
                }

                $entityTotals['generated_variants'] += count($result['generated']);
                $entityTotals['skipped_variants'] += count($result['skipped']);
            }
        });

        foreach ($entityTotals as $key => $value) {
            $totals[$key] += $value;
        }

        $this->line(sprintf(
            '%s : %d générée(s), %d skipped, %d source(s) manquante(s), %d échec(s).',
            $label,
            $entityTotals['generated_variants'],
            $entityTotals['skipped_variants'],
            $entityTotals['missing_sources'],
            $entityTotals['failed_rows'],
        ));
    }
}
