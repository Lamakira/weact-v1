<?php

declare(strict_types=1);

namespace App\Support\StaleSelectionReconcile;

use RuntimeException;

/**
 * Marker exception thrown when a payment moved out of scope between discovery
 * and the lockForUpdate (e.g. fedapay_transaction_id became non-null). The
 * outer loop catches it and skips the payment without aborting the run.
 */
final class SkipPayment extends RuntimeException {}
