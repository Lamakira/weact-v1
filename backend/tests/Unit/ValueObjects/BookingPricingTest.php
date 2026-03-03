<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\BookingPricing;
use PHPUnit\Framework\TestCase;

class BookingPricingTest extends TestCase
{
    // === BASIC CALCULATION ===

    public function test_basic_calculation_50000_base(): void
    {
        $pricing = new BookingPricing(50000);

        $this->assertSame(50000, $pricing->baseTarif);
        $this->assertSame(7500, $pricing->producerCommission);
        $this->assertSame(57500, $pricing->totalProducerPays);
        $this->assertSame(7500, $pricing->faceCommission);
        $this->assertSame(42500, $pricing->faceReceives);
        $this->assertSame(15000, $pricing->platformRevenue);
    }

    // === ROUNDING ===

    public function test_rounding_for_fractional_commission(): void
    {
        // 33333 * 0.15 = 4999.95 → rounds to 5000
        $pricing = new BookingPricing(33333);

        $this->assertSame(33333, $pricing->baseTarif);
        $this->assertSame(5000, $pricing->producerCommission);
        $this->assertSame(38333, $pricing->totalProducerPays);
        $this->assertSame(5000, $pricing->faceCommission);
        $this->assertSame(28333, $pricing->faceReceives);
        $this->assertSame(10000, $pricing->platformRevenue);
    }

    public function test_rounding_rounds_half_up(): void
    {
        // 10 * 0.15 = 1.5 → rounds to 2
        $pricing = new BookingPricing(10);

        $this->assertSame(2, $pricing->producerCommission);
        $this->assertSame(12, $pricing->totalProducerPays);
        $this->assertSame(2, $pricing->faceCommission);
        $this->assertSame(8, $pricing->faceReceives);
        $this->assertSame(4, $pricing->platformRevenue);
    }

    // === ZERO AMOUNT ===

    public function test_zero_base_returns_all_zeros(): void
    {
        $pricing = new BookingPricing(0);

        $this->assertSame(0, $pricing->baseTarif);
        $this->assertSame(0, $pricing->producerCommission);
        $this->assertSame(0, $pricing->totalProducerPays);
        $this->assertSame(0, $pricing->faceCommission);
        $this->assertSame(0, $pricing->faceReceives);
        $this->assertSame(0, $pricing->platformRevenue);
    }

    // === SMALL AMOUNT ===

    public function test_small_amount_100_base(): void
    {
        // 100 * 0.15 = 15 → exact
        $pricing = new BookingPricing(100);

        $this->assertSame(100, $pricing->baseTarif);
        $this->assertSame(15, $pricing->producerCommission);
        $this->assertSame(115, $pricing->totalProducerPays);
        $this->assertSame(15, $pricing->faceCommission);
        $this->assertSame(85, $pricing->faceReceives);
        $this->assertSame(30, $pricing->platformRevenue);
    }

    // === LARGE AMOUNT ===

    public function test_large_amount_1000000_base(): void
    {
        // 1,000,000 * 0.15 = 150,000 → exact
        $pricing = new BookingPricing(1_000_000);

        $this->assertSame(1_000_000, $pricing->baseTarif);
        $this->assertSame(150_000, $pricing->producerCommission);
        $this->assertSame(1_150_000, $pricing->totalProducerPays);
        $this->assertSame(150_000, $pricing->faceCommission);
        $this->assertSame(850_000, $pricing->faceReceives);
        $this->assertSame(300_000, $pricing->platformRevenue);
    }

    // === READONLY PROPERTIES ===

    public function test_all_properties_are_readonly_and_accessible(): void
    {
        $pricing = new BookingPricing(50000);

        // All properties should be accessible
        $this->assertIsInt($pricing->baseTarif);
        $this->assertIsInt($pricing->producerCommission);
        $this->assertIsInt($pricing->totalProducerPays);
        $this->assertIsInt($pricing->faceCommission);
        $this->assertIsInt($pricing->faceReceives);
        $this->assertIsInt($pricing->platformRevenue);
    }

    public function test_readonly_properties_cannot_be_modified(): void
    {
        $pricing = new BookingPricing(50000);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $pricing->baseTarif = 99999;
    }

    // === INVARIANTS ===

    public function test_totalProducerPays_equals_baseTarif_plus_producerCommission(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base);
            $this->assertSame(
                $pricing->baseTarif + $pricing->producerCommission,
                $pricing->totalProducerPays,
                "Invariant failed for base={$base}"
            );
        }
    }

    public function test_faceReceives_equals_baseTarif_minus_faceCommission(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base);
            $this->assertSame(
                $pricing->baseTarif - $pricing->faceCommission,
                $pricing->faceReceives,
                "Invariant failed for base={$base}"
            );
        }
    }

    public function test_platformRevenue_equals_both_commissions(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base);
            $this->assertSame(
                $pricing->producerCommission + $pricing->faceCommission,
                $pricing->platformRevenue,
                "Invariant failed for base={$base}"
            );
        }
    }
}
