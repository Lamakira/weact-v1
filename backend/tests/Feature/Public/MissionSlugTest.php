<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionSlugTest extends TestCase
{
    use RefreshDatabase;

    private function createProducerWithUser(): Producer
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return $producer;
    }

    // ─── Slug Auto-Generation ────────────────────────────────────────

    public function test_slug_is_auto_generated_on_mission_create(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Casting publicité MTN',
        ]);

        $this->assertNotNull($mission->slug);
        $this->assertEquals('casting-publicite-mtn', $mission->slug);
    }

    public function test_slug_handles_collision_with_suffix(): void
    {
        $producer = $this->createProducerWithUser();

        $mission1 = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Casting Spot TV',
        ]);

        $mission2 = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Casting Spot TV',
        ]);

        $this->assertEquals('casting-spot-tv', $mission1->slug);
        $this->assertEquals('casting-spot-tv-2', $mission2->slug);
    }

    public function test_slug_handles_multiple_collisions(): void
    {
        $producer = $this->createProducerWithUser();

        $mission1 = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission',
        ]);
        $mission2 = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission',
        ]);
        $mission3 = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission',
        ]);

        $this->assertEquals('test-mission', $mission1->slug);
        $this->assertEquals('test-mission-2', $mission2->slug);
        $this->assertEquals('test-mission-3', $mission3->slug);
    }

    public function test_slug_regenerates_only_when_title_changes(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Original Title',
        ]);

        $originalSlug = $mission->slug;

        // Update a non-title field
        $mission->update(['budget' => 999999]);
        $this->assertEquals($originalSlug, $mission->fresh()->slug);

        // Update the title
        $mission->update(['titre' => 'New Title']);
        $this->assertEquals('new-title', $mission->fresh()->slug);
    }

    public function test_slug_handles_empty_or_special_characters(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => '!!!???',
        ]);

        // Empty slug from Str::slug falls back to 'mission'
        $this->assertEquals('mission', $mission->slug);
    }

    public function test_slug_preserves_when_manually_set(): void
    {
        $producer = $this->createProducerWithUser();

        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'My Mission',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $mission->slug);
    }

    // ─── Slug Uniqueness Tests ──────────────────────────────────────

    public function test_slug_is_unique_in_database(): void
    {
        $producer = $this->createProducerWithUser();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Unique Test',
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Unique Test',
        ]);

        $slugs = Mission::pluck('slug')->toArray();
        $this->assertCount(2, array_unique($slugs));
    }

    // ─── API Response Includes Slug ─────────────────────────────────

    public function test_missions_list_response_includes_slug(): void
    {
        $producer = $this->createProducerWithUser();

        Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'titre' => 'Test Mission For List',
        ]);

        $response = $this->getJson('/api/v1/public/missions');

        $response->assertOk();
        $this->assertArrayHasKey('slug', $response->json('data.0'));
        $this->assertEquals('test-mission-for-list', $response->json('data.0.slug'));
    }
}
