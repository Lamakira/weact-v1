<?php

declare(strict_types=1);

namespace App\Support\LegacyAttendanceSettlement;

use RuntimeException;

/**
 * Marker exception thrown at the end of a dry-run mission processing closure
 * to force a clean rollback of the surrounding DB::transaction. Strict mirror
 * of App\Support\StaleSelectionReconcile\DryRunCompleted; no payload is required
 * here because the per-mission counter is incremented by the caller after catch.
 */
final class DryRunCompleted extends RuntimeException {}
