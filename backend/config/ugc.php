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
    // Durées des chronos livrables (FR6/AR11), en jours — déclenchées par
    // « Produit reçu » (recu_le, story 3.3). `avis` est posé dès maintenant
    // pour figer le symbole mais n'est consommé qu'à l'épic 4 (la validation
    // de l'Unboxing démarre le chrono Avis).
    'deliverable_days' => [
        'unboxing' => 7,
        'avis' => 14,
    ],
];
