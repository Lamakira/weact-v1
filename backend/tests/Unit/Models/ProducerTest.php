<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_missions_count_returns_zero_when_no_missions(): void
    {
        $producer = Producer::factory()->create();

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_returns_correct_count_for_published_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->count(3)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $this->assertEquals(3, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_draft_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_closed_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_excludes_completed_missions(): void
    {
        $producer = Producer::factory()->create();

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $this->assertEquals(0, $producer->published_missions_count);
    }

    public function test_published_missions_count_only_counts_published_in_mixed_statuses(): void
    {
        $producer = Producer::factory()->create();

        // 2 published
        Mission::factory()->count(2)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // 1 draft
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        // 1 closed
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        // 1 completed
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $this->assertEquals(2, $producer->published_missions_count);
    }

    public function test_published_missions_count_does_not_count_other_producers_missions(): void
    {
        $producer1 = Producer::factory()->create();
        $producer2 = Producer::factory()->create();

        // Producer 1 has 2 published missions
        Mission::factory()->count(2)->create([
            'producer_id' => $producer1->id,
            'status' => MissionStatus::Published,
        ]);

        // Producer 2 has 3 published missions
        Mission::factory()->count(3)->create([
            'producer_id' => $producer2->id,
            'status' => MissionStatus::Published,
        ]);

        $this->assertEquals(2, $producer1->published_missions_count);
        $this->assertEquals(3, $producer2->published_missions_count);
    }
}
