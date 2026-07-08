<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Face;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckFaceListingRanksFreshnessCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faces:check-listing-ranks-freshness';

    /**
     * The console command description.
     */
    protected $description = 'Alert (Log::critical + FAILURE) when the materialized public-listing ranking has not been rebuilt within the freshness threshold — a silently failing nightly rebuild freezes the rotation.';

    /**
     * Two missed nightly rebuilds: one failure can be a fluke, two in a row
     * is an incident. The rebuild itself already logs each failure; this
     * watchdog exists because NO log line is emitted when the scheduler
     * simply stops invoking the rebuild at all.
     */
    private const MAX_AGE_HOURS = 48;

    /**
     * Execute the console command.
     *
     * Degraded mode is deliberately invisible to visitors (the previous
     * generation keeps being served), so freshness must be watched from the
     * outside: fires hourly Log::critical entries while the ranking is
     * stale, giving monitoring a repeating, unambiguous signal.
     */
    public function handle(): int
    {
        $latestGeneratedAt = DB::table('face_listing_ranks')->max('created_at');

        if ($latestGeneratedAt === null) {
            // Empty table is healthy only while there is nothing to rank.
            if (! Face::query()->publiclyListable()->exists()) {
                return self::SUCCESS;
            }

            Log::critical('Face listing ranking has never been built while listable Faces exist — the public listing is running on the id-DESC fallback', [
                'action' => 'run `php artisan faces:rebuild-listing-ranks` and check the scheduler',
            ]);
            $this->error('Ranking never built while listable Faces exist.');

            return self::FAILURE;
        }

        $lastBuiltAt = Carbon::parse($latestGeneratedAt);

        if ($lastBuiltAt->greaterThanOrEqualTo(now()->subHours(self::MAX_AGE_HOURS))) {
            return self::SUCCESS;
        }

        Log::critical('Face listing ranking is stale — the nightly rebuild has not succeeded within the freshness threshold, the public rotation is frozen', [
            'last_built_at' => $lastBuiltAt->toDateTimeString(),
            'threshold_hours' => self::MAX_AGE_HOURS,
            'action' => 'check the faces:rebuild-listing-ranks scheduler runs and their logs',
        ]);
        $this->error("Ranking is stale: last built {$lastBuiltAt->toDateTimeString()} (threshold ".self::MAX_AGE_HOURS.'h).');

        return self::FAILURE;
    }
}
