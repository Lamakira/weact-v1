<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ugc;

use App\Services\Ugc\UgcCommissionService;
use Tests\TestCase;

/**
 * Pure calculation test for the UGC commission (no DB).
 * Boots the Laravel app only so config('ugc.*') resolves; RefreshDatabase is intentionally NOT used.
 */
class UgcCommissionServiceTest extends TestCase
{
    private UgcCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UgcCommissionService;
    }

    public function test_floor_applies_when_percentage_below_floor(): void
    {
        // 20000 × 0.10 = 2000 < 2500 → plancher
        $this->assertSame(2500, $this->service->compute(20000));
    }

    public function test_exact_floor_boundary(): void
    {
        // 25000 × 0.10 = 2500 → borne exacte
        $this->assertSame(2500, $this->service->compute(25000));
    }

    public function test_percentage_applies_above_floor(): void
    {
        // 50000 × 0.10 = 5000 > 2500 → pourcentage
        $this->assertSame(5000, $this->service->compute(50000));
    }

    public function test_round_half_up_above_floor(): void
    {
        // 25005 × 0.10 = 2500.5 → round half-up = 2501 (> plancher)
        $this->assertSame(2501, $this->service->compute(25005));
    }

    public function test_hybrid_commission_at_free_tier_rate(): void
    {
        // Hybride : commission sur le CASH au palier Free (0.15) — 15000 × 0.15 = 2250.
        $this->assertSame(2250, $this->service->computeHybrid(15000, 0.15));
    }

    public function test_hybrid_commission_at_starter_pro_tier_rate(): void
    {
        // Palier Starter/Pro (0.10) — 15000 × 0.10 = 1500.
        $this->assertSame(1500, $this->service->computeHybrid(15000, 0.10));
    }

    public function test_hybrid_commission_at_elite_tier_rate(): void
    {
        // Palier Élite (0.05) — 15000 × 0.05 = 750.
        $this->assertSame(750, $this->service->computeHybrid(15000, 0.05));
    }

    public function test_hybrid_commission_rounds_half_up(): void
    {
        // 10001 × 0.15 = 1500.15 → round = 1500.
        $this->assertSame(1500, $this->service->computeHybrid(10001, 0.15));
    }

    public function test_hybrid_commission_has_no_floor(): void
    {
        // D-RH1.d : pas de plancher sur le cash hybride. 1000 × 0.05 = 50 (surtout PAS 2500).
        $this->assertSame(50, $this->service->computeHybrid(1000, 0.05));
    }
}
