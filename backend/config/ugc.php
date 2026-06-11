<?php

declare(strict_types=1);

return [
    // Commission producteur UGC : 10 % de la valeur produit, plancher 2 500 FCFA (OI-1 : unique au publish).
    'commission_rate' => 0.10,
    'commission_floor' => 2500,
    // Nombre de vidéos figé pour « produit seul » (1 Unboxing + 1 Avis).
    'product_only_video_count' => 2,
    // Fenêtre d'acceptation Face d'un booking UGC payé, en jours (ancrée sur
    // commission_paid_at — D-2.5.c). Passé ce délai : expiration + demande de
    // remboursement de la commission Producteur.
    'acceptance_window_days' => 7,
];
