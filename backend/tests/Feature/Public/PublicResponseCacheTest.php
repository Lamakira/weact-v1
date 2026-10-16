<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Face;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * `public.cache` middleware (CachePublicResponse): 60 s response cache on the
 * public GET endpoints, no invalidation.
 */
class PublicResponseCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.public_response_ttl' => 60,
            'cache.public_response_store' => 'array',
        ]);
        Cache::store('array')->flush();
    }

    private function makeFace(): Face
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return $face;
    }

    public function test_second_identical_request_is_a_hit_with_identical_body(): void
    {
        $this->makeFace();

        $first = $this->getJson('/api/v1/public/faces');
        $second = $this->getJson('/api/v1/public/faces');

        $first->assertOk()->assertHeader('X-Public-Cache', 'MISS');
        $second->assertOk()->assertHeader('X-Public-Cache', 'HIT');
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_hit_keeps_status_and_content_type(): void
    {
        $this->makeFace();

        $first = $this->getJson('/api/v1/public/faces');
        $second = $this->getJson('/api/v1/public/faces');

        $second->assertStatus(200);
        $this->assertSame($first->headers->get('Content-Type'), $second->headers->get('Content-Type'));
        $this->assertStringContainsString('application/json', (string) $second->headers->get('Content-Type'));
    }

    public function test_cached_body_is_stale_until_the_store_is_flushed(): void
    {
        $this->makeFace();

        $first = $this->getJson('/api/v1/public/faces');
        $this->makeFace();

        $stale = $this->getJson('/api/v1/public/faces');
        $stale->assertHeader('X-Public-Cache', 'HIT');
        $this->assertSame($first->getContent(), $stale->getContent());
        $this->assertSame(1, $stale->json('meta.total'));

        Cache::store('array')->flush();

        $fresh = $this->getJson('/api/v1/public/faces');
        $fresh->assertHeader('X-Public-Cache', 'MISS');
        $this->assertSame(2, $fresh->json('meta.total'));
    }

    public function test_different_query_string_is_a_miss(): void
    {
        $this->getJson('/api/v1/public/faces?page=1')->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson('/api/v1/public/faces?page=2')->assertHeader('X-Public-Cache', 'MISS');
    }

    public function test_same_params_in_different_order_share_the_entry(): void
    {
        $this->getJson('/api/v1/public/faces?page=1&per_page=5')->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson('/api/v1/public/faces?per_page=5&page=1')->assertHeader('X-Public-Cache', 'HIT');
    }

    public function test_not_found_is_never_cached(): void
    {
        $this->getJson('/api/v1/public/faces/inconnu-xyz')
            ->assertNotFound()
            ->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson('/api/v1/public/faces/inconnu-xyz')
            ->assertNotFound()
            ->assertHeader('X-Public-Cache', 'MISS');

        $this->getJson('/api/v1/public/missions/inconnue-xyz')->assertNotFound()->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson('/api/v1/public/missions/inconnue-xyz')->assertNotFound()->assertHeader('X-Public-Cache', 'MISS');

        $this->getJson('/api/v1/public/articles/inconnu-xyz')->assertNotFound()->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson('/api/v1/public/articles/inconnu-xyz')->assertNotFound()->assertHeader('X-Public-Cache', 'MISS');
    }

    public function test_post_route_never_carries_the_header(): void
    {
        $response = $this->postJson('/api/v1/public/contact', []);

        $this->assertFalse($response->headers->has('X-Public-Cache'));
    }

    public function test_ttl_zero_disables_the_cache(): void
    {
        config(['cache.public_response_ttl' => 0]);

        $first = $this->getJson('/api/v1/public/faces');
        $second = $this->getJson('/api/v1/public/faces');

        $this->assertFalse($first->headers->has('X-Public-Cache'));
        $this->assertFalse($second->headers->has('X-Public-Cache'));
    }

    public function test_profile_detail_is_cached(): void
    {
        $face = $this->makeFace();

        $this->getJson("/api/v1/public/faces/{$face->username}")->assertOk()->assertHeader('X-Public-Cache', 'MISS');
        $this->getJson("/api/v1/public/faces/{$face->username}")->assertOk()->assertHeader('X-Public-Cache', 'HIT');
    }
}
