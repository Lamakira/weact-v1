<?php

declare(strict_types=1);

namespace App\ValueObjects;

class BookingPricing
{
    /**
     * Producer-side platform fee. Flat, tier-independent: the producer always
     * pays base + 10 % regardless of the booked Face's subscription tier.
     */
    private const PRODUCER_COMMISSION_RATE = 0.10;

    public readonly int $baseTarif;

    public readonly int $totalProducerPays;

    public readonly int $faceReceives;

    public readonly int $producerCommission;

    public readonly int $faceCommission;

    public readonly int $platformRevenue;

    /**
     * Face-side commission rate actually applied (tier-driven, resolved by the
     * caller via FaceEntitlementService::capabilities($face)->commissionRate).
     * Exposed for auditability / financial-event logging.
     */
    public readonly float $faceCommissionRate;

    public function __construct(int $baseTarif, float $faceCommissionRate)
    {
        $this->baseTarif = $baseTarif;
        $this->faceCommissionRate = $faceCommissionRate;
        $this->producerCommission = (int) round($baseTarif * self::PRODUCER_COMMISSION_RATE);
        $this->faceCommission = (int) round($baseTarif * $faceCommissionRate);
        $this->totalProducerPays = $baseTarif + $this->producerCommission;
        $this->faceReceives = $baseTarif - $this->faceCommission;
        $this->platformRevenue = $this->producerCommission + $this->faceCommission;
    }
}
