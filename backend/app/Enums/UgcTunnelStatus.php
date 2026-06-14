<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Micro-tunnel UGC post-expédition, porté par Shipment.tunnel_status (D2/AR2).
 * L'amont (paiement, acceptation) se lit sur BookingStatus/CandidatureStatus.
 * Écriture par story : Shipped = 3.1 ; Received = 3.3 ; UnboxingInReview = 4.1 ;
 * AvisPending / AvisInReview / Completed = 4.3 (validation Producteur Unboxing →
 * chrono Avis → clôture, + branche reject/retouche) ; Overdue / Suspended
 * réservés au moteur de deadlines (4.5/5.1). JAMAIS dans une Rule::in sur input
 * client (pas de values(), délibéré) ; pas de match exhaustif hors label()
 * (toujours un default).
 */
enum UgcTunnelStatus: string
{
    case Shipped = 'shipped';
    case Received = 'received';
    case UnboxingInReview = 'unboxing_in_review';
    case AvisPending = 'avis_pending';
    case AvisInReview = 'avis_in_review';
    case Completed = 'completed';
    case Overdue = 'overdue';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Shipped => 'Produit expédié',
            self::Received => 'Produit reçu',
            self::UnboxingInReview => 'Unboxing en validation',
            self::AvisPending => 'Avis attendu',
            self::AvisInReview => 'Avis en validation',
            self::Completed => 'Terminé',
            self::Overdue => 'Délai dépassé',
            self::Suspended => 'Suspendu',
        };
    }
}
