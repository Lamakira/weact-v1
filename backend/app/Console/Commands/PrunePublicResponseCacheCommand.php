<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The file cache store never deletes expired files by itself (an expired key is
 * only removed when it is read again), and the `public.cache` key includes the
 * whole query string: arbitrary params would grow the directory forever.
 *
 * Deletes files of the public response store older than max(2 x TTL, 600) s,
 * then the emptied hash subdirectories. No-op unless that store is a file store;
 * never touches anything outside a directory named `public-responses`.
 */
class PrunePublicResponseCacheCommand extends Command
{
    protected $signature = 'cache:prune-public-responses';

    protected $description = 'Delete stale files from the public response file cache (no-op for other cache drivers).';

    public function handle(): int
    {
        $store = (string) config('cache.public_response_store');

        if (config("cache.stores.{$store}.driver") !== 'file') {
            $this->info("Cache store [{$store}] is not file-backed: nothing to prune.");

            return self::SUCCESS;
        }

        $path = rtrim((string) config("cache.stores.{$store}.path"), '/');

        if (basename($path) !== 'public-responses') {
            $this->error("Refusing to prune [{$path}]: the directory must be named public-responses.");

            return self::FAILURE;
        }

        if (! is_dir($path)) {
            $this->info('Public response cache directory does not exist: nothing to prune.');

            return self::SUCCESS;
        }

        $threshold = time() - max(2 * (int) config('cache.public_response_ttl'), 600);
        $deleted = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                if ($item->getMTime() < $threshold && @unlink($item->getPathname())) {
                    $deleted++;
                }
            } elseif ($item->isDir() && ! $item->isLink()) {
                // CHILD_FIRST: children are already handled; rmdir only succeeds on an empty directory.
                @rmdir($item->getPathname());
            }
        }

        $this->info("Pruned {$deleted} stale public response cache file(s).");

        return self::SUCCESS;
    }
}
