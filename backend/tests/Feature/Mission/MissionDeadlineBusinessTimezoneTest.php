<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MissionDeadlineBusinessTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createMissionClosingOn10October(): Mission
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return Mission::factory()->published()->create([
            'producer_id' => $producer->id,
            'date_limite_candidature' => '2026-10-10',
            'date_tournage' => '2026-11-15',
        ]);
    }

    public function test_deadline_day_is_over_after_benin_midnight_even_if_still_utc_day(): void
    {
        // 23:30 UTC le 10/10 = 00:30 le 11/10 au Bénin
        Carbon::setTestNow(Carbon::parse('2026-10-10 23:30:00', 'UTC'));
        $mission = $this->createMissionClosingOn10October();

        $this->assertFalse($mission->isAcceptingCandidatures());
        $this->assertFalse(Mission::notExpired()->whereKey($mission->id)->exists());
        $this->assertFalse(Mission::acceptingCandidatures()->whereKey($mission->id)->exists());

        $list = $this->getJson('/api/v1/public/missions')->assertOk();
        $this->assertNotContains($mission->slug, collect($list->json('data'))->pluck('slug')->all());

        $this->getJson("/api/v1/public/missions/{$mission->slug}")->assertNotFound();
    }

    public function test_deadline_day_is_still_open_before_benin_midnight(): void
    {
        // 22:30 UTC le 10/10 = 23:30 le 10/10 au Bénin
        Carbon::setTestNow(Carbon::parse('2026-10-10 22:30:00', 'UTC'));
        $mission = $this->createMissionClosingOn10October();

        $this->assertTrue($mission->isAcceptingCandidatures());
        $this->assertTrue(Mission::notExpired()->whereKey($mission->id)->exists());
        $this->assertTrue(Mission::acceptingCandidatures()->whereKey($mission->id)->exists());

        $list = $this->getJson('/api/v1/public/missions')->assertOk();
        $this->assertContains($mission->slug, collect($list->json('data'))->pluck('slug')->all());

        $this->getJson("/api/v1/public/missions/{$mission->slug}")->assertOk();
    }
}
