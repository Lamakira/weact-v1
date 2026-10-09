<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The database cache store never deletes expired rows by itself (an expired key
 * is only overwritten when it is written again): the table grows forever.
 * No-op unless the default cache store uses the `database` driver.
 */
class PurgeExpiredCacheCommand extends Command
{
    protected $signature = 'cache:purge-expired';

    protected $description = 'Delete expired rows from the database cache and cache lock tables (no-op for other cache drivers).';

    public function handle(): int
    {
        $store = (string) config('cache.default');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            $this->info("Cache store [{$store}] is not database-backed: nothing to purge.");

            return self::SUCCESS;
        }

        $now = now()->getTimestamp();
        $connection = config("cache.stores.{$store}.connection");
        $table = (string) config("cache.stores.{$store}.table", 'cache');
        $lockConnection = config("cache.stores.{$store}.lock_connection") ?? $connection;
        $lockTable = (string) (config("cache.stores.{$store}.lock_table") ?? 'cache_locks');

        $cacheRows = DB::connection($connection)->table($table)->where('expiration', '<', $now)->delete();
        $lockRows = DB::connection($lockConnection)->table($lockTable)->where('expiration', '<', $now)->delete();

        $this->info("Purged {$cacheRows} expired cache row(s) and {$lockRows} expired lock row(s).");

        return self::SUCCESS;
    }
}
