<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialEventType: string
{
    case PaymentInitiated = 'payment_initiated';
    case PaymentConfirmed = 'payment_confirmed';
    case PaymentFailed = 'payment_failed';
}
