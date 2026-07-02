<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Type de livrable vidéo d'un deal UGC. Le tunnel anti-arnaque porte EXACTEMENT
 * 2 livrables canoniques fixes sous chrono : `unboxing` (7 j) + `avis` (14 j)
 * — décision PO option B (2026-06-13). Les vidéos 3..N d'un deal sont
 * contractuelles HORS tunnel (pas de Deliverable) : `extra` est donc
 * délibérément ABSENT (D-4.1.a), pas réservé pour plus tard.
 *
 * JAMAIS dans une Rule::in sur input client (pas de values(), calque
 * garde-fou UgcTunnelStatus) : le `kind` est posé serveur, jamais reçu.
 */
enum DeliverableKind: string
{
    case Unboxing = 'unboxing';
    case Avis = 'avis';

    public function label(): string
    {
        return match ($this) {
            self::Unboxing => 'Unboxing',
            self::Avis => 'Avis',
        };
    }
}
