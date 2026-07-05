<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\FaceSubscriptionPaidAtBackfill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillFaceSubscriptionPaidAtCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'face-subscriptions:backfill-paid-at';

    /**
     * @var string
     */
    protected $description = 'Rattrape paid_at depuis metadata.confirmed_at pour les lignes payées non datées (idempotent, re-exécutable après déploiement)';

    public function handle(): int
    {
        $result = FaceSubscriptionPaidAtBackfill::run();

        Log::info('face_subscriptions paid_at backfill terminé', [
            'backfilled_rows' => $result['backfilled'],
            'non_datable_rows' => $result['non_datable'],
        ]);

        $this->info(sprintf(
            'Backfill paid_at terminé : %d ligne(s) datée(s), %d non-datable(s).',
            $result['backfilled'],
            $result['non_datable'],
        ));

        return self::SUCCESS;
    }
}
