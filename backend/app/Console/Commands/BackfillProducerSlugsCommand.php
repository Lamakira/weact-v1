<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Producer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillProducerSlugsCommand extends Command
{
    protected $signature = 'app:backfill-producer-slugs {--chunk=500}';

    protected $description = 'Backfill slug column for existing producers from display_name';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $updated = 0;

        Producer::whereNull('slug')->chunkById($chunkSize, function ($producers) use (&$updated): void {
            foreach ($producers as $producer) {
                $base = Str::slug($producer->display_name ?: 'producer');
                $slug = $base;
                $counter = 1;

                while (Producer::where('slug', $slug)->where('id', '!=', $producer->id)->exists()) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                $producer->slug = $slug;
                $producer->timestamps = false;
                $producer->save();
                $updated++;
            }
        });

        $this->info("Backfilled {$updated} producer slugs.");

        return self::SUCCESS;
    }
}
