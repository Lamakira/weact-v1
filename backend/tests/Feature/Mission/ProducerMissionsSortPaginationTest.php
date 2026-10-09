<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tri / pagination serveur (opt-in) de GET /api/v1/producer/missions.
 */
class ProducerMissionsSortPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function mission(array $attrs = []): Mission
    {
        $createdAt = $attrs['created_at'] ?? null;
        unset($attrs['created_at']);

        $mission = Mission::factory()->create(array_merge([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ], $attrs));

        if ($createdAt !== null) {
            DB::table('missions')->where('id', $mission->id)->update(['created_at' => $createdAt]);
        }

        return $mission;
    }

    /**
     * @return list<string>
     */
    private function ids(string $query): array
    {
        return collect(
            $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?'.$query)->assertOk()->json('data')
        )->pluck('id')->all();
    }

    public function test_default_response_stays_unpaginated_and_ordered_by_created_at_desc(): void
    {
        $old = $this->mission(['created_at' => now()->subDays(3)]);
        $new = $this->mission(['created_at' => now()->subDay()]);

        $response = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->assertOk();

        $this->assertSame([$new->uuid, $old->uuid], collect($response->json('data'))->pluck('id')->all());
        $this->assertNull($response->json('meta'));
        $this->assertNull($response->json('links'));
    }

    public function test_sort_created_at(): void
    {
        $a = $this->mission(['created_at' => now()->subDays(3)]);
        $b = $this->mission(['created_at' => now()->subDays(1)]);
        $c = $this->mission(['created_at' => now()->subDays(2)]);

        $this->assertSame([$a->uuid, $c->uuid, $b->uuid], $this->ids('sort=created_at&direction=asc'));
        $this->assertSame([$b->uuid, $c->uuid, $a->uuid], $this->ids('sort=created_at&direction=desc'));
    }

    public function test_sort_date_tournage_with_nulls_last(): void
    {
        $a = $this->mission(['date_tournage' => now()->addDays(10)->toDateString()]);
        $b = $this->mission(['date_tournage' => now()->addDays(5)->toDateString()]);
        $ugc = $this->mission();
        DB::table('missions')->where('id', $ugc->id)->update(['date_tournage' => null]);

        $this->assertSame([$b->uuid, $a->uuid, $ugc->uuid], $this->ids('sort=date_tournage&direction=asc'));
        $this->assertSame([$a->uuid, $b->uuid, $ugc->uuid], $this->ids('sort=date_tournage&direction=desc'));
    }

    public function test_sort_date_limite_candidature(): void
    {
        $a = $this->mission(['date_limite_candidature' => now()->addDays(4)->toDateString(), 'date_tournage' => now()->addDays(20)->toDateString()]);
        $b = $this->mission(['date_limite_candidature' => now()->addDays(2)->toDateString(), 'date_tournage' => now()->addDays(20)->toDateString()]);

        $this->assertSame([$b->uuid, $a->uuid], $this->ids('sort=date_limite_candidature&direction=asc'));
        $this->assertSame([$a->uuid, $b->uuid], $this->ids('sort=date_limite_candidature&direction=desc'));
    }

    public function test_sort_status(): void
    {
        $published = $this->mission(['status' => MissionStatus::Published]);
        $closed = $this->mission(['status' => MissionStatus::Closed]);
        $completed = $this->mission(['status' => MissionStatus::Completed]);

        // missions.status est une colonne ENUM MySQL : tri par ordre de cycle de vie
        // (draft, published, pending_payment, closed, pending_attendance_validation, completed).
        $this->assertSame([$published->uuid, $closed->uuid, $completed->uuid], $this->ids('sort=status&direction=asc'));
        $this->assertSame([$completed->uuid, $closed->uuid, $published->uuid], $this->ids('sort=status&direction=desc'));
    }

    public function test_sort_candidatures_count(): void
    {
        $none = $this->mission();
        $two = $this->mission();
        $one = $this->mission();
        Candidature::factory()->count(2)->create(['mission_id' => $two->id]);
        Candidature::factory()->count(1)->create(['mission_id' => $one->id]);

        $this->assertSame([$none->uuid, $one->uuid, $two->uuid], $this->ids('sort=candidatures_count&direction=asc'));
        $this->assertSame([$two->uuid, $one->uuid, $none->uuid], $this->ids('sort=candidatures_count&direction=desc'));
    }

    public function test_paginated_response_with_per_page_and_status_filter_combined_with_sort(): void
    {
        $m1 = $this->mission(['status' => MissionStatus::Closed, 'created_at' => now()->subDays(3)]);
        $m2 = $this->mission(['status' => MissionStatus::Closed, 'created_at' => now()->subDays(2)]);
        $m3 = $this->mission(['status' => MissionStatus::Closed, 'created_at' => now()->subDays(1)]);
        $this->mission(['status' => MissionStatus::Published]);

        $page1 = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions?status=closed&sort=created_at&direction=asc&per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertSame([$m1->uuid, $m2->uuid, $m3->uuid], collect($page1->json('data'))->pluck('id')->all());

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions?status=closed&per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pagination_is_opt_in_and_page_alone_uses_default_per_page(): void
    {
        Mission::factory()->count(16)->create(['producer_id' => $this->producer->id]);

        $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')
            ->assertOk()->assertJsonCount(16, 'data');
        $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?page=1')
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.total', 16);
    }

    public function test_message_and_resource_shape_are_kept_when_paginated(): void
    {
        $this->mission();

        $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('message', 'Missions récupérées avec succès')
            ->assertJsonStructure(['data' => ['*' => ['id', 'titre', 'status', 'candidatures_count', 'has_paid_payment']]]);
    }

    public function test_other_producers_missions_never_leak(): void
    {
        $mine = $this->mission();
        Mission::factory()->count(2)->create(['producer_id' => Producer::factory()->create()->id]);

        $this->assertSame([$mine->uuid], $this->ids('page=1&per_page=10&sort=status'));
    }

    public function test_invalid_params_are_rejected_with_422(): void
    {
        foreach ([
            'sort=budget', 'sort=created_at&direction=up', 'per_page=7', 'status=bogus', 'page=0', 'sort[]=status',
        ] as $query) {
            $this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions?'.$query)
                ->assertStatus(422);
        }
    }

    public function test_sorted_paginated_query_count_is_constant(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->producerUser)
                ->getJson('/api/v1/producer/missions?page=1&per_page=50&sort=candidatures_count&direction=desc')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->mission();
        $this->mission();
        $small = $count();
        for ($i = 0; $i < 6; $i++) {
            $this->mission();
        }
        $large = $count();

        $this->assertSame($small, $large, "Producer missions queries grew: {$small} -> {$large}");
    }
}
