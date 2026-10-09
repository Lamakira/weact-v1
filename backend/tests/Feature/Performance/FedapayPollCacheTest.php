<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use App\Support\FedapayPollCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PERF-B4: bounded FedaPay HTTP calls, and one remote read per 15 s on polled endpoints.
 */
class FedapayPollCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_mission_payment_status_polled_twice_reads_fedapay_once(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::PendingPayment,
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
            'fedapay_transaction_id' => '123456',
            'status' => MissionPaymentStatus::Pending,
        ]);

        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = 'pending';

        $this->mock(FedapayService::class, function ($mock) use ($transaction): void {
            $mock->shouldReceive('retrieveTransaction')->once()->with(123456)->andReturn($transaction);
        });

        $url = "/api/v1/producer/missions/{$mission->uuid}/payment-status";
        $this->actingAs($producerUser)->getJson($url)->assertOk()->assertJsonPath('data.is_trackable', true);
        $this->actingAs($producerUser)->getJson($url)->assertOk()->assertJsonPath('data.is_trackable', true);
    }

    public function test_only_pending_statuses_are_cached(): void
    {
        $calls = 0;
        $fetch = function () use (&$calls): object {
            $calls++;

            return (object) ['id' => 1, 'status' => 'approved', 'reference' => 'ref'];
        };

        FedapayPollCache::remember(77, $fetch);
        FedapayPollCache::remember(77, $fetch);

        $this->assertSame(2, $calls, 'An approved status must always be read live (it drives a settlement).');

        $pendingCalls = 0;
        $pending = function () use (&$pendingCalls): object {
            $pendingCalls++;

            return (object) ['id' => 2, 'status' => 'pending', 'reference' => null, 'amount' => 1100];
        };

        $first = FedapayPollCache::remember(78, $pending);
        $second = FedapayPollCache::remember(78, $pending);

        $this->assertSame(1, $pendingCalls);
        $this->assertSame('pending', $second->status);
        $this->assertSame(1100, $second->amount);
        $this->assertSame($first->status, $second->status);
    }
}
