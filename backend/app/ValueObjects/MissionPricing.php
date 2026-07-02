<?php

declare(strict_types=1);

namespace App\ValueObjects;

class MissionPricing
{
    /**
     * Producer-side platform fee. Flat, tier-independent: the producer always
     * pays subtotal + 10 % regardless of the selected Faces' subscription tiers.
     *
     * The Face-side commission is NOT modelled here. It is resolved per-Face in
     * MissionPaymentService::prepareSelectionForPayment using each Face's tier
     * rate (FaceEntitlementService::capabilities($face)->commissionRate). This
     * value object deliberately exposes no uniform `montantParFace` so the
     * per-Face split cannot be bypassed (FP-3.1b).
     */
    private const PRODUCER_COMMISSION_RATE = 0.10;

    public readonly int $budgetParFace;

    public readonly int $nombreFaces;

    public readonly int $sousTotal;

    public readonly int $commissionProducteur;

    public readonly int $montantTotalProducteur;

    public function __construct(int $budgetParFace, int $nombreFaces)
    {
        $this->budgetParFace = $budgetParFace;
        $this->nombreFaces = $nombreFaces;
        $this->sousTotal = $budgetParFace * $nombreFaces;
        $this->commissionProducteur = (int) round($this->sousTotal * self::PRODUCER_COMMISSION_RATE);
        $this->montantTotalProducteur = $this->sousTotal + $this->commissionProducteur;
    }
}
