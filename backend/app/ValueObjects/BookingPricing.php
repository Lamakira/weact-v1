<?php

declare(strict_types=1);

namespace App\ValueObjects;

class BookingPricing
{
    private const COMMISSION_RATE = 0.10;

    public readonly int $baseTarif;

    public readonly int $totalProducerPays;

    public readonly int $faceReceives;

    public readonly int $producerCommission;

    public readonly int $faceCommission;

    public readonly int $platformRevenue;

    public function __construct(int $baseTarif)
    {
        $this->baseTarif = $baseTarif;
        $this->producerCommission = (int) round($baseTarif * self::COMMISSION_RATE);
        $this->faceCommission = (int) round($baseTarif * self::COMMISSION_RATE);
        $this->totalProducerPays = $baseTarif + $this->producerCommission;
        $this->faceReceives = $baseTarif - $this->faceCommission;
        $this->platformRevenue = $this->producerCommission + $this->faceCommission;
    }
}
