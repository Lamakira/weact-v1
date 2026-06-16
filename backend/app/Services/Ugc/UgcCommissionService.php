<?php

declare(strict_types=1);

namespace App\Services\Ugc;

class UgcCommissionService
{
    /**
     * Commission WeAct sur une dotation UGC PRODUIT SEUL : max(plancher, round(valeur * taux)).
     * Assise sur la valeur produit UNIQUEMENT (jamais sur le cash de la rémunération).
     */
    public function compute(int $valeurProduit): int
    {
        $rate = (float) config('ugc.commission_rate');
        $floor = (int) config('ugc.commission_floor');

        return max($floor, (int) round($valeurProduit * $rate));
    }

    /**
     * Commission WeAct sur le CASH d'un booking UGC hybride : round(cash * tauxPalierFace).
     * Au taux d'abonnement de la Face (15/10/5 %), JAMAIS sur la valeur produit, et
     * SANS plancher (≠ produit seul, D-RH1.d). Le taux est résolu par l'appelant
     * (FaceEntitlementService::capabilities($face)->commissionRate) — ce service reste sans DB.
     */
    public function computeHybrid(int $montantRemuneration, float $faceCommissionRate): int
    {
        return (int) round($montantRemuneration * $faceCommissionRate);
    }
}
