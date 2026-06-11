<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when the FedaPay transaction.refunded webhook settles a UGC
 * commission refund (commission_refunded_at posé).
 */
class UgcCommissionRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking|Mission $owner,
    ) {}
}
