<?php

declare(strict_types=1);

namespace App\Services\Ugc;

class UgcCommissionService
{
    /**
     * Commission WeAct sur une dotation UGC : max(plancher, round(valeur * taux)).
     * Assise sur la valeur produit UNIQUEMENT (jamais sur le cash de la rémunération).
     */
    public function compute(int $valeurProduit): int
    {
        $rate = (float) config('ugc.commission_rate');
        $floor = (int) config('ugc.commission_floor');

        return max($floor, (int) round($valeurProduit * $rate));
    }
}
