<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched (post-commit) when a UGC commission refund is settled via wallet
 * credit (commission_refunded_at posé + crédit wallet Producteur — story 2.6).
 */
class UgcCommissionRefunded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking|Mission $owner,
    ) {}
}
