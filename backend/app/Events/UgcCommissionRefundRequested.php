<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a UGC commission refund request is recorded
 * (commission_refund_requested_at posé — refus Face, fenêtre d'acceptation
 * expirée ou mission terminée sans participant). Le remboursement réel est un
 * flux OPS manuel (spike OI-2 : SDK FedaPay sans refund()).
 */
class UgcCommissionRefundRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking|Mission $owner,
    ) {}
}
