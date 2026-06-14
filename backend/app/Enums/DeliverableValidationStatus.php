<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut de validation d'un livrable vidéo UGC. 4.1 n'écrit QUE `in_review`
 * (la ligne naît à l'upload, D-4.1.b) ; `validated` / `rejected` /
 * `retouche_requested` sont posés dès maintenant pour figer le symbole mais
 * ne sont consommés qu'en 4.3 (validation Producteur).
 *
 * Pas de `pending_upload` (D-4.1.b) : avant upload, l'état « attendu » est
 * dérivé du tunnel (Shipment `received` + deadline dérivée), jamais persisté.
 */
enum DeliverableValidationStatus: string
{
    case InReview = 'in_review';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case RetoucheRequested = 'retouche_requested';

    public function label(): string
    {
        return match ($this) {
            self::InReview => 'En attente de validation',
            self::Validated => 'Validée',
            self::Rejected => 'Refusée',
            self::RetoucheRequested => 'Retouche demandée',
        };
    }
}
