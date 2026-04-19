<?php

declare(strict_types=1);

namespace App\Support\StaleSelectionReconcile;

use RuntimeException;

/**
 * Marker exception thrown at the end of a dry-run payment closure to force a
 * clean rollback of the surrounding DB::transaction. The outcome payload is
 * attached so the outer loop can still render the summary.
 */
final class DryRunCompleted extends RuntimeException
{
    /** @var array<string, mixed>|null */
    public ?array $outcome = null;
}
