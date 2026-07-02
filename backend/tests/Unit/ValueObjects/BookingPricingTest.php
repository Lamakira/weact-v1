<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\BookingPricing;
use PHPUnit\Framework\TestCase;

class BookingPricingTest extends TestCase
{
    // === BASIC CALCULATION (Starter/Pro path — Face rate 10 %) ===

    public function test_basic_calculation_50000_base(): void
    {
        $pricing = new BookingPricing(50000, 0.10);

        $this->assertSame(50000, $pricing->baseTarif);
        $this->assertSame(5000, $pricing->producerCommission);
        $this->assertSame(55000, $pricing->totalProducerPays);
        $this->assertSame(5000, $pricing->faceCommission);
        $this->assertSame(45000, $pricing->faceReceives);
        $this->assertSame(10000, $pricing->platformRevenue);
    }

    // === ROUNDING ===

    public function test_rounding_for_fractional_commission(): void
    {
        // 33333 * 0.10 = 3333.3 → rounds to 3333
        $pricing = new BookingPricing(33333, 0.10);

        $this->assertSame(33333, $pricing->baseTarif);
        $this->assertSame(3333, $pricing->producerCommission);
        $this->assertSame(36666, $pricing->totalProducerPays);
        $this->assertSame(3333, $pricing->faceCommission);
        $this->assertSame(30000, $pricing->faceReceives);
        $this->assertSame(6666, $pricing->platformRevenue);
    }

    public function test_rounding_rounds_half_up(): void
    {
        // 15 * 0.10 = 1.5 → rounds to 2
        $pricing = new BookingPricing(15, 0.10);

        $this->assertSame(2, $pricing->producerCommission);
        $this->assertSame(17, $pricing->totalProducerPays);
        $this->assertSame(2, $pricing->faceCommission);
        $this->assertSame(13, $pricing->faceReceives);
        $this->assertSame(4, $pricing->platformRevenue);
    }

    // === ZERO AMOUNT ===

    public function test_zero_base_returns_all_zeros(): void
    {
        $pricing = new BookingPricing(0, 0.10);

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
        // 100 * 0.10 = 10 → exact
        $pricing = new BookingPricing(100, 0.10);

        $this->assertSame(100, $pricing->baseTarif);
        $this->assertSame(10, $pricing->producerCommission);
        $this->assertSame(110, $pricing->totalProducerPays);
        $this->assertSame(10, $pricing->faceCommission);
        $this->assertSame(90, $pricing->faceReceives);
        $this->assertSame(20, $pricing->platformRevenue);
    }

    // === LARGE AMOUNT ===

    public function test_large_amount_1000000_base(): void
    {
        // 1,000,000 * 0.10 = 100,000 → exact
        $pricing = new BookingPricing(1_000_000, 0.10);

        $this->assertSame(1_000_000, $pricing->baseTarif);
        $this->assertSame(100_000, $pricing->producerCommission);
        $this->assertSame(1_100_000, $pricing->totalProducerPays);
        $this->assertSame(100_000, $pricing->faceCommission);
        $this->assertSame(900_000, $pricing->faceReceives);
        $this->assertSame(200_000, $pricing->platformRevenue);
    }

    // === TIER-AWARE FACE COMMISSION (FP-3.1a) ===

    public function test_decouverte_face_commission_is_15_percent(): void
    {
        // Découverte (free) Face: 15 % cut on the Face side, producer still 10 %.
        $pricing = new BookingPricing(50000, 0.15);

        $this->assertSame(50000, $pricing->baseTarif);
        $this->assertSame(5000, $pricing->producerCommission);   // unchanged: 10 %
        $this->assertSame(55000, $pricing->totalProducerPays);   // 50000 + 5000
        $this->assertSame(7500, $pricing->faceCommission);       // 50000 × 0.15
        $this->assertSame(42500, $pricing->faceReceives);        // 50000 − 7500
        $this->assertSame(12500, $pricing->platformRevenue);     // 5000 + 7500
    }

    public function test_elite_face_commission_is_5_percent(): void
    {
        // Élite Face: 5 % cut on the Face side, producer still 10 %.
        $pricing = new BookingPricing(50000, 0.05);

        $this->assertSame(50000, $pricing->baseTarif);
        $this->assertSame(5000, $pricing->producerCommission);   // unchanged: 10 %
        $this->assertSame(55000, $pricing->totalProducerPays);
        $this->assertSame(2500, $pricing->faceCommission);       // 50000 × 0.05
        $this->assertSame(47500, $pricing->faceReceives);        // 50000 − 2500
        $this->assertSame(7500, $pricing->platformRevenue);      // 5000 + 2500
    }

    public function test_producer_commission_is_independent_of_face_rate(): void
    {
        foreach ([0.05, 0.10, 0.15] as $faceRate) {
            $pricing = new BookingPricing(50000, $faceRate);

            $this->assertSame(5000, $pricing->producerCommission, "Producer commission must stay 10 % for faceRate={$faceRate}");
            $this->assertSame(55000, $pricing->totalProducerPays, "Producer total must stay base + 10 % for faceRate={$faceRate}");
        }
    }

    public function test_face_commission_rate_is_exposed(): void
    {
        $pricing = new BookingPricing(50000, 0.15);

        $this->assertSame(0.15, $pricing->faceCommissionRate);
    }

    // === READONLY PROPERTIES ===

    public function test_all_properties_are_readonly_and_accessible(): void
    {
        $pricing = new BookingPricing(50000, 0.10);

        // All properties should be accessible
        $this->assertIsInt($pricing->baseTarif);
        $this->assertIsInt($pricing->producerCommission);
        $this->assertIsInt($pricing->totalProducerPays);
        $this->assertIsInt($pricing->faceCommission);
        $this->assertIsInt($pricing->faceReceives);
        $this->assertIsInt($pricing->platformRevenue);
        $this->assertIsFloat($pricing->faceCommissionRate);
    }

    public function test_readonly_properties_cannot_be_modified(): void
    {
        $pricing = new BookingPricing(50000, 0.10);

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $pricing->baseTarif = 99999;
    }

    // === INVARIANTS ===

    public function test_total_producer_pays_equals_base_tarif_plus_producer_commission(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base, 0.10);
            $this->assertSame(
                $pricing->baseTarif + $pricing->producerCommission,
                $pricing->totalProducerPays,
                "Invariant failed for base={$base}"
            );
        }
    }

    public function test_face_receives_equals_base_tarif_minus_face_commission(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base, 0.10);
            $this->assertSame(
                $pricing->baseTarif - $pricing->faceCommission,
                $pricing->faceReceives,
                "Invariant failed for base={$base}"
            );
        }
    }

    public function test_platform_revenue_equals_both_commissions(): void
    {
        foreach ([1000, 25000, 50000, 100000] as $base) {
            $pricing = new BookingPricing($base, 0.10);
            $this->assertSame(
                $pricing->producerCommission + $pricing->faceCommission,
                $pricing->platformRevenue,
                "Invariant failed for base={$base}"
            );
        }
    }
}
