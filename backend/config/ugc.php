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
    // Disque de stockage des médias livrables : disque PRIVÉ `local`
    // (storage/app/private, `serve => true`) — distinct du `public` du
    // portfolio FaceVideo (D7 / AC5). La lecture Producteur (URL signée) = 4.4.
    'storage_disk' => 'local',
    // Contraintes de validation du fichier livrable, lues par
    // UploadDeliverableRequest. `max_duration_seconds = null` : pas de plafond
    // de durée pour un livrable (un Avis peut être long — question PO #3).
    'media' => [
        'max_size_mb' => 200,
        'allowed_extensions' => ['mp4', 'mov', 'avi'],
        'allowed_mimetypes' => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
        'max_duration_seconds' => null,
    ],
    // SLA de validation Producteur d'un livrable (heures), ancré sur
    // Deliverable.submitted_at (entrée — ou ré-entrée — en in_review). TRACÉ
    // SEULEMENT (exposé via DeliverableResource.review_due_at) : aucun
    // enforcement en 4.3 (relance/auto-action = job dédié, calque 4.5).
    'deliverable_review_sla_hours' => 48,
];
