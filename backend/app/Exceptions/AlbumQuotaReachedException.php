<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a Face attempts to add an album photo beyond its entitlement-resolved upload limit.
 */
class AlbumQuotaReachedException extends \RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(sprintf('Quota de %d photos atteint.', $limit));
    }
}
