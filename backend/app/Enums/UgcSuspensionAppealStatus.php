<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut de l'appel/réactivation d'une suspension UGC. La story 5.1 n'écrit que
 * `None` (suspension créée, pas encore d'appel) ; `Pending/Accepted/Rejected`
 * sont posés dès maintenant (colonne + enum) pour le cycle d'appel de la story
 * 5.3 (régularisation / dégel) — évite une migration ultérieure.
 */
enum UgcSuspensionAppealStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun appel',
            self::Pending => 'Appel en cours',
            self::Accepted => 'Appel accepté',
            self::Rejected => 'Appel rejeté',
        };
    }
}
