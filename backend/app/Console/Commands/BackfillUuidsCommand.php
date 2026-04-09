<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BackfillUuidsCommand extends Command
{
    protected $signature = 'app:backfill-uuids
                            {model : Fully qualified model class (e.g. App\\Models\\Booking)}
                            {--chunk=500 : Number of records to process per chunk}';

    protected $description = 'Backfill UUID column for existing records of a given model';

    public function handle(): int
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $updated = 0;

        $modelClass::whereNull('uuid')->chunkById($chunkSize, function ($records) use (&$updated): void {
            foreach ($records as $record) {
                $record->uuid = (string) Str::uuid();
                $record->timestamps = false;
                $record->save();
                $updated++;
            }
        });

        $this->info("Backfilled {$updated} records for {$modelClass}.");

        return self::SUCCESS;
    }
}
