<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Mission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A mission is published for the FIRST time (published_at just set).
 * Fired inside the saving transaction ; the queued listener has `$afterCommit`, so the job is only pushed once it commits. Never fired on reopening.
 */
class MissionPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Mission $mission,
    ) {}
}
