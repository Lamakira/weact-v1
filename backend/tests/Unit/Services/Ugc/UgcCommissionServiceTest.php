<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ugc;

use App\Services\Ugc\UgcCommissionService;
use PHPUnit\Framework\Attributes\DataProvider;
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
        // 20000 × 0.05 = 1000 < 2500 → plancher
        $this->assertSame(2500, $this->service->compute(20000));
    }

    public function test_exact_floor_boundary(): void
    {
        // 50000 × 0.05 = 2500 → borne exacte
        $this->assertSame(2500, $this->service->compute(50000));
    }

    public function test_percentage_applies_above_floor(): void
    {
        // 100000 × 0.05 = 5000 > 2500 → pourcentage
        $this->assertSame(5000, $this->service->compute(100000));
    }

    public function test_round_half_up_above_floor(): void
    {
        // 50010 × 0.05 = 2500.5 → round half-up = 2501 (> plancher)
        $this->assertSame(2501, $this->service->compute(50010));
    }

    /**
     * Grille de référence validée PO : max(2500, round(valeur × 0,05)).
     *
     * @return array<string, array{int, int}>
     */
    public static function referenceGrid(): array
    {
        return [
            '10 000 → plancher' => [10000, 2500],
            '30 000 → plancher' => [30000, 2500],
            '50 000 → plancher (borne)' => [50000, 2500],
            '100 000 → 5 %' => [100000, 5000],
            '200 000 → 5 %' => [200000, 10000],
        ];
    }

    #[DataProvider('referenceGrid')]
    public function test_reference_grid(int $valeur, int $attendu): void
    {
        $this->assertSame($attendu, $this->service->compute($valeur));
    }
}
