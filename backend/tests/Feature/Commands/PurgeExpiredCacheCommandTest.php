<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurgeExpiredCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedRows(): void
    {
        $now = now()->getTimestamp();

        DB::table('cache')->insert([
            ['key' => 'expired-a', 'value' => 's:1:"a";', 'expiration' => $now - 100],
            ['key' => 'expired-b', 'value' => 's:1:"b";', 'expiration' => $now - 1],
            ['key' => 'live', 'value' => 's:1:"c";', 'expiration' => $now + 3600],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'lock-expired', 'owner' => 'x', 'expiration' => $now - 100],
            ['key' => 'lock-live', 'owner' => 'y', 'expiration' => $now + 3600],
        ]);
    }

    public function test_it_deletes_only_expired_rows_when_the_store_is_database(): void
    {
        config(['cache.default' => 'database']);
        $this->seedRows();

        $this->artisan('cache:purge-expired')->assertExitCode(0);

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['lock-live'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_it_does_nothing_when_the_store_is_not_database(): void
    {
        config(['cache.default' => 'array']);
        $this->seedRows();

        $this->artisan('cache:purge-expired')->assertExitCode(0);

        $this->assertSame(3, DB::table('cache')->count());
        $this->assertSame(2, DB::table('cache_locks')->count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'cache:purge-expired'));

        $this->assertCount(1, $events);
        $this->assertSame('0 4 * * *', $events->first()->expression);
    }
}
