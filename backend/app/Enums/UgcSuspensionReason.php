<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Motif d'une suspension douce UGC (épic 5, story 5.1). Dérivé de l'état actif
 * du shipment au dépassement du chrono : Received → Unboxing, AvisPending → Avis.
 */
enum UgcSuspensionReason: string
{
    case UnboxingDeadlineMissed = 'unboxing_deadline_missed';
    case AvisDeadlineMissed = 'avis_deadline_missed';

    public function label(): string
    {
        return match ($this) {
            self::UnboxingDeadlineMissed => 'Unboxing non livré dans les délais',
            self::AvisDeadlineMissed => 'Avis non livré dans les délais',
        };
    }
}
