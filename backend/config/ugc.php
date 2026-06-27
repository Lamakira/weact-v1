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
    // Fenêtre de réactivation « terminer en retard » d'une suspension UGC (J+30,
    // architecture-ugc.md:148 / D5). Source UNIQUE : 5.2 l'affiche
    // (reactivation_deadline = suspended_at + N jours), 5.3 l'ENFORCE (cutoff de
    // dégel). Ne pas hardcoder 30 ailleurs (front compris).
    'late_completion_days' => 30,
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
    // TTL (minutes) des URLs vidéo/miniature SIGNÉES servies au Producteur sur
    // l'écran de validation 5A (UGC 4.4). Le disque privé `local` ne supporte
    // pas Storage::temporaryUrl (S3-only) → URL::temporarySignedRoute + route
    // signée hors api.token (un <video src> natif ne porte pas le header). TTL
    // court : borne la fuite d'une URL ; le refetch post-action la renouvelle.
    'video_url_ttl_minutes' => 30,
    // Seuils d'escalade des notifications de deadline livrable (4.5, OI-5 — PO-tunable).
    // Fractions de progress ASCENDANTES, alignées sur les paliers couleur du front
    // (ChronoBadge/ChronoRing : 0.4 ambre, 0.6 orange, 0.85 rouge ; teal = base, sans
    // notif). `shipments.last_notified_threshold` = nb de paliers déjà notifiés (0..N).
    'deadline_escalation_thresholds' => [0.4, 0.6, 0.85],
    // Fenêtre de reconfirmation post-acceptation d'une candidature mission UGC (heures,
    // ugc-9-1, D-9.1.b). Ancrée sur `candidatures.accepted_at`. Passé ce délai sans
    // reconfirmation, le sweep `ugc:expire-unreconfirmed-candidatures` dénoue la
    // candidature (refund escrow hybride au Producteur + libère le slot + réouverture).
    // Fallback 48 en dur (calque ExpireUnacceptedUgcDealsCommand : une clé absente
    // donnerait subHours(0) → cutoff = now() → balayage massif de tout l'encours).
    'reconfirm_window_hours' => 48,
];
