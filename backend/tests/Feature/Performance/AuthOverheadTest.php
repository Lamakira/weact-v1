<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Face;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B5: one token lookup per request, last_used_at written at most once a minute.
 */
class AuthOverheadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $this->plainToken = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * @return list<string>
     */
    private function tokenQueries(): array
    {
        return collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $q): bool => str_contains($q, 'personal_access_tokens'))
            ->values()
            ->all();
    }

    public function test_user_endpoint_looks_the_token_up_once(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withToken($this->plainToken)->getJson('/api/v1/user')->assertOk();
        DB::disableQueryLog();

        $selects = array_filter($this->tokenQueries(), fn (string $q): bool => str_starts_with($q, 'select'));
        $this->assertCount(1, $selects, 'Token SELECTs: '.implode(' | ', $selects));
    }

    public function test_last_used_at_is_written_at_most_once_per_minute(): void
    {
        $this->withToken($this->plainToken)->getJson('/api/v1/user')->assertOk();
        $firstUse = PersonalAccessToken::query()->firstOrFail()->last_used_at;
        $this->assertNotNull($firstUse);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->travel(30)->seconds();
        $this->app['auth']->forgetGuards(); // fresh request: the guard memoises the resolved user
        $this->withToken($this->plainToken)->getJson('/api/v1/user')->assertOk();
        DB::disableQueryLog();

        $updates = array_filter($this->tokenQueries(), fn (string $q): bool => str_starts_with($q, 'update'));
        $this->assertCount(0, $updates, 'No write within the minute');
        $this->assertEquals($firstUse, PersonalAccessToken::query()->firstOrFail()->last_used_at);

        $this->travel(61)->seconds();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->plainToken)->getJson('/api/v1/user')->assertOk();

        $this->assertTrue(PersonalAccessToken::query()->firstOrFail()->last_used_at->gt($firstUse));
    }

    public function test_expired_or_unknown_tokens_are_still_refused(): void
    {
        $this->withToken('999|unknown')->getJson('/api/v1/user')->assertUnauthorized();

        PersonalAccessToken::query()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($this->plainToken)->getJson('/api/v1/user')->assertUnauthorized();
    }
}
