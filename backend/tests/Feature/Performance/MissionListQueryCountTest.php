<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B3 (+B1 for the embedded producer): mission lists do not query per row.
 */
class MissionListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function newProducer(): Producer
    {
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return $producer;
    }

    private function addMission(Producer $producer, bool $paid): Mission
    {
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 1000,
            'montant_sous_total' => 1000,
            'commission_producteur' => 100,
            'montant_total_producteur' => 1100,
            'commission_faces_total' => 100,
            'montant_total_faces' => 900,
            'status' => $paid ? MissionPaymentStatus::Paid : MissionPaymentStatus::Pending,
            'paid_at' => $paid ? now() : null,
        ]);

        return $mission;
    }

    private function queryCount(User $user, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_face_missions_index_query_count_is_constant(): void
    {
        $this->addMission($this->newProducer(), true);
        $this->addMission($this->newProducer(), false);
        $small = $this->queryCount($this->faceUser, '/api/v1/face/missions');

        for ($i = 0; $i < 6; $i++) {
            $this->addMission($this->newProducer(), $i % 2 === 0);
        }
        $large = $this->queryCount($this->faceUser, '/api/v1/face/missions');

        $this->assertSame($small, $large, "Face missions queries grew: {$small} -> {$large}");
    }

    public function test_producer_missions_index_query_count_is_constant_and_flag_is_preserved(): void
    {
        $producer = $this->producerUser->userable;
        $paid = $this->addMission($producer, true);
        $pending = $this->addMission($producer, false);
        $small = $this->queryCount($this->producerUser, '/api/v1/producer/missions');

        for ($i = 0; $i < 6; $i++) {
            $this->addMission($producer, $i % 2 === 0);
        }
        $large = $this->queryCount($this->producerUser, '/api/v1/producer/missions');

        $this->assertSame($small, $large, "Producer missions queries grew: {$small} -> {$large}");

        $byId = collect($this->actingAs($this->producerUser)->getJson('/api/v1/producer/missions')->json('data'))
            ->keyBy('id');
        $this->assertTrue($byId[$paid->uuid]['has_paid_payment']);
        $this->assertFalse($byId[$pending->uuid]['has_paid_payment']);
    }
}
