<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Services\Auth\GoogleOAuthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Single-use entries and the cache rows they leave behind.
 */
class GoogleOAuthCacheHygieneTest extends TestCase
{
    use RefreshDatabase;

    private const PRUNE_EVENT = 'cache:prune-expired-database-rows';

    private GoogleOAuthService $oauth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oauth = app(GoogleOAuthService::class);
    }

    private function exchangeKey(string $code): string
    {
        return 'oauth:xchg:'.hash('sha256', $code);
    }

    public function test_the_spent_marker_only_outlives_the_race_window(): void
    {
        $code = $this->oauth->issueExchangeCode(['kind' => 'authenticated', 'user_id' => 1]);
        $key = $this->exchangeKey($code);

        $this->assertNotNull($this->oauth->consumeExchangeCode($code));
        $this->assertTrue(Cache::has($key.':spent'));
        $this->assertFalse(Cache::has($key));

        $this->travel(61)->seconds();

        // On the database store an expired row is only deleted when that very key is
        // read: a marker that lingers would pile up forever.
        $this->assertFalse(Cache::has($key.':spent'));
    }

    /**
     * Simulates the concurrent winner: it already took the `:spent` marker but has
     * not yet forgotten the value, so the loser reads it and must still get nothing.
     */
    public function test_a_code_whose_spent_marker_is_already_taken_yields_nothing(): void
    {
        $code = $this->oauth->issueExchangeCode(['kind' => 'authenticated', 'user_id' => 1]);

        Cache::put($this->exchangeKey($code).':spent', true, 60);

        $this->assertNull($this->oauth->consumeExchangeCode($code));
    }

    public function test_the_daily_prune_deletes_expired_database_cache_rows_and_keeps_live_ones(): void
    {
        config(['cache.default' => 'database']);

        $table = (string) config('cache.stores.database.table');

        DB::table($table)->insert([
            ['key' => 'oauth:state:expired', 'value' => 's:1:"1";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'oauth:state:live', 'value' => 's:1:"1";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === self::PRUNE_EVENT);

        $this->assertNotNull($event, 'The prune event is not scheduled.');

        $event->run($this->app);

        $this->assertDatabaseMissing($table, ['key' => 'oauth:state:expired']);
        $this->assertDatabaseHas($table, ['key' => 'oauth:state:live']);
    }

    public function test_the_daily_prune_is_a_no_op_on_another_cache_store(): void
    {
        config(['cache.default' => 'array']);

        $table = (string) config('cache.stores.database.table');

        DB::table($table)->insert([
            ['key' => 'untouched', 'value' => 's:1:"1";', 'expiration' => now()->subMinute()->getTimestamp()],
        ]);

        $this->artisan('schedule:list')->assertSuccessful();

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => $event->description === self::PRUNE_EVENT);

        $this->assertNotNull($event);

        $event->run($this->app);

        $this->assertDatabaseHas($table, ['key' => 'untouched']);
    }
}
