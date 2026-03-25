<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a concurrent FedaPay withdrawal attempt is detected via Cache::lock.
 */
class WithdrawalLockException extends RuntimeException {}
