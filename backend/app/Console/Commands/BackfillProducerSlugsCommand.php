<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Producer;
use Illuminate\Console\Command;

class BackfillProducerSlugsCommand extends Command
{
    protected $signature = 'app:backfill-producer-slugs {--chunk=500}';

    protected $description = 'Backfill slug column for existing producers';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $updated = 0;

        Producer::whereNull('slug')->chunkById($chunkSize, function ($producers) use (&$updated): void {
            foreach ($producers as $producer) {
                $producer->slug = Producer::generateUniqueSlug($producer->slugSourceName(), $producer->id);
                $producer->timestamps = false;
                $producer->save();
                $updated++;
            }
        });

        $this->info("Backfilled {$updated} producer slugs.");

        return self::SUCCESS;
    }
}
