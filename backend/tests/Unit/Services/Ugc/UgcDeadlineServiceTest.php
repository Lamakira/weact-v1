<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ugc;

use App\Enums\DeliverableKind;
use App\Enums\UgcTunnelStatus;
use App\Models\Shipment;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pure logic test for the UGC deadline escalation engine (4.5). Boots the
 * Laravel app only so config('ugc.*') resolves; RefreshDatabase is intentionally
 * NOT used — progressFor/escalationLevelFor are computed on in-memory models and
 * an explicit $now (no DB). The avis_pending positive branch (DB query) is
 * covered by the Feature test.
 */
class UgcDeadlineServiceTest extends TestCase
{
    private UgcDeadlineService $service;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UgcDeadlineService;
        $this->now = Carbon::parse('2026-06-15T12:00:00+00:00');
    }

    // ===================================================================
    // escalationLevelFor — paliers ASCENDANTS [0.4, 0.6, 0.85]
    // ===================================================================

    public function test_escalation_level_is_zero_below_first_threshold(): void
    {
        $this->assertSame(0, $this->service->escalationLevelFor(0.0));
        $this->assertSame(0, $this->service->escalationLevelFor(0.39));
    }

    public function test_escalation_level_at_each_threshold_boundary(): void
    {
        $this->assertSame(1, $this->service->escalationLevelFor(0.4));
        $this->assertSame(1, $this->service->escalationLevelFor(0.59));
        $this->assertSame(2, $this->service->escalationLevelFor(0.6));
        $this->assertSame(2, $this->service->escalationLevelFor(0.84));
        $this->assertSame(3, $this->service->escalationLevelFor(0.85));
    }

    public function test_escalation_level_caps_at_threshold_count(): void
    {
        $this->assertSame(3, $this->service->escalationLevelFor(1.0));
    }

    // ===================================================================
    // progressFor — clamp [0,1] sur le chrono Unboxing (received)
    // ===================================================================

    public function test_progress_is_zero_before_the_chrono_starts(): void
    {
        $shipment = $this->receivedShipment($this->now->copy()->addDays(1)); // start dans le futur

        $this->assertSame(0.0, $this->service->progressFor($shipment, $this->now));
    }

    public function test_progress_is_one_after_the_deadline(): void
    {
        $shipment = $this->receivedShipment($this->now->copy()->subDays(10)); // deadline = now - 3 j

        $this->assertSame(1.0, $this->service->progressFor($shipment, $this->now));
    }

    public function test_progress_is_half_at_mid_window(): void
    {
        // recu_le = now - 3,5 j ; span Unboxing = 7 j → 0.5 exact.
        $shipment = $this->receivedShipment($this->now->copy()->subDays(3)->subHours(12));

        $this->assertSame(0.5, $this->service->progressFor($shipment, $this->now));
    }

    public function test_progress_is_null_without_an_active_chrono(): void
    {
        $shipment = $this->receivedShipment($this->now);
        $shipment->tunnel_status = UgcTunnelStatus::Completed;

        $this->assertNull($this->service->progressFor($shipment, $this->now));
    }

    // ===================================================================
    // chronoWindowFor — null hors {received, avis_pending}
    // ===================================================================

    public function test_chrono_window_is_null_outside_active_upload_states(): void
    {
        $shipment = $this->receivedShipment($this->now);

        foreach ([
            UgcTunnelStatus::UnboxingInReview,
            UgcTunnelStatus::AvisInReview,
            UgcTunnelStatus::Completed,
            UgcTunnelStatus::Overdue,
            UgcTunnelStatus::Suspended,
        ] as $status) {
            $shipment->tunnel_status = $status;
            $this->assertNull($this->service->chronoWindowFor($shipment), "expected null window for {$status->value}");
        }
    }

    public function test_chrono_window_is_null_when_received_without_recu_le(): void
    {
        $shipment = new Shipment;
        $shipment->tunnel_status = UgcTunnelStatus::Received;
        $shipment->recu_le = null;

        $this->assertNull($this->service->chronoWindowFor($shipment));
    }

    public function test_chrono_window_for_received_derives_unboxing_window(): void
    {
        $recuLe = $this->now->copy()->subDays(2);
        $shipment = $this->receivedShipment($recuLe);

        $window = $this->service->chronoWindowFor($shipment);

        $this->assertNotNull($window);
        $this->assertSame(DeliverableKind::Unboxing, $window['kind']);
        $this->assertSame($recuLe->toIso8601String(), $window['start']->toIso8601String());
        // deadline = recu_le + 7 j (config ugc.deliverable_days.unboxing).
        $this->assertSame($recuLe->copy()->addDays(7)->toIso8601String(), $window['deadline']->toIso8601String());
    }

    private function receivedShipment(Carbon $recuLe): Shipment
    {
        $shipment = new Shipment;
        $shipment->tunnel_status = UgcTunnelStatus::Received;
        $shipment->recu_le = $recuLe;

        return $shipment;
    }
}
