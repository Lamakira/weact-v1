<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\FaceVideoType;

/**
 * Thrown when a Face attempts to add a portfolio video beyond its tier's per-type quota.
 */
class VideoQuotaReachedException extends \RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly FaceVideoType $type,
    ) {
        parent::__construct(
            $limit < 1
                ? sprintf('Votre formule actuelle ne permet pas de vidéo %s.', $type->label())
                : sprintf('Quota de %d vidéo%s %s atteint.', $limit, $limit > 1 ? 's' : '', $type->label())
        );
    }
}
