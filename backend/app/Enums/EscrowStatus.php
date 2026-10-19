<?php

declare(strict_types=1);

namespace App\Enums;

enum EscrowStatus: string
{
    case Pending = 'pending';
    case Locked = 'locked';
    case Released = 'released';
    case Refunded = 'refunded';
    // Lot B4 : l'argent d'une place libérée est passé à l'entry de la remplaçante (même
    // paiement). Terminal : ni séquestre, ni versement, ni remboursement.
    case Transferred = 'transferred';
}
