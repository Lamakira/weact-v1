<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\MissionPricing;
use PHPUnit\Framework\TestCase;

class MissionPricingTest extends TestCase
{
    public function test_producer_commission_is_ten_percent_of_subtotal(): void
    {
        $pricing = new MissionPricing(100000, 3);

        $this->assertSame(100000, $pricing->budgetParFace);
        $this->assertSame(3, $pricing->nombreFaces);
        $this->assertSame(300000, $pricing->sousTotal);
        $this->assertSame(30000, $pricing->commissionProducteur);     // 10 % of 300000
        $this->assertSame(330000, $pricing->montantTotalProducteur);  // 300000 + 30000
    }

    public function test_single_face_subtotal_equals_budget(): void
    {
        $pricing = new MissionPricing(50000, 1);

        $this->assertSame(50000, $pricing->sousTotal);
        $this->assertSame(5000, $pricing->commissionProducteur);
        $this->assertSame(55000, $pricing->montantTotalProducteur);
    }

    public function test_producer_commission_rounds_half_up(): void
    {
        // sousTotal 33335 -> 10 % = 3333.5 -> round() = 3334
        $pricing = new MissionPricing(33335, 1);

        $this->assertSame(33335, $pricing->sousTotal);
        $this->assertSame(3334, $pricing->commissionProducteur);
        $this->assertSame(36669, $pricing->montantTotalProducteur);
    }
}
