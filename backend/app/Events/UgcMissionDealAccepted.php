<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Candidature;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a Face directly accepts a UGC mission deal
 * (candidature lands confirmed — UGC 2.4, FR6 étape 2). Entry point of the
 * UGC tunnel: epics 3-5 (shipment, chronos, suspension) hook their own
 * listeners here (AR8). Dispatched AFTER the acceptance transaction commits.
 */
class UgcMissionDealAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Candidature $candidature,
    ) {}
}
