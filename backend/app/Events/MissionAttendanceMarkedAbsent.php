<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\MissionPaymentCandidature;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MissionAttendanceMarkedAbsent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MissionPaymentCandidature $entry,
    ) {}
}
