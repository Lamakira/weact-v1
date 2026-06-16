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
}
