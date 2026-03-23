<?php

declare(strict_types=1);

namespace App\ValueObjects;

class MissionPricing
{
    private const COMMISSION_RATE = 0.15;

    public readonly int $budgetParFace;
    public readonly int $nombreFaces;
    public readonly int $sousTotal;
    public readonly int $commissionProducteur;
    public readonly int $montantTotalProducteur;
    public readonly int $commissionFacesTotal;
    public readonly int $montantTotalFaces;
    public readonly int $montantParFace;

    public function __construct(int $budgetParFace, int $nombreFaces)
    {
        $this->budgetParFace = $budgetParFace;
        $this->nombreFaces = $nombreFaces;
        $this->sousTotal = $budgetParFace * $nombreFaces;
        $this->commissionProducteur = (int) round($this->sousTotal * self::COMMISSION_RATE);
        $this->montantTotalProducteur = $this->sousTotal + $this->commissionProducteur;
        $this->commissionFacesTotal = (int) round($this->sousTotal * self::COMMISSION_RATE);
        $this->montantTotalFaces = $this->sousTotal - $this->commissionFacesTotal;
        $this->montantParFace = $nombreFaces > 0
            ? (int) round($this->montantTotalFaces / $nombreFaces)
            : 0;
    }
}
