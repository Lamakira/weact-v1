<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialEventType: string
{
    case PaymentInitiated = 'payment_initiated';
    case PaymentConfirmed = 'payment_confirmed';
    case PaymentFailed = 'payment_failed';
    case EscrowLock = 'escrow_lock';
    case EscrowRelease = 'escrow_release';
    case Refund = 'refund';
    case Withdrawal = 'withdrawal';
    case Commission = 'commission';

    public function label(): string
    {
        return match ($this) {
            self::PaymentInitiated => 'Paiement initié',
            self::PaymentConfirmed => 'Paiement confirmé',
            self::PaymentFailed => 'Paiement échoué',
            self::EscrowLock => 'Escrow verrouillé',
            self::EscrowRelease => 'Escrow libéré',
            self::Refund => 'Remboursement',
            self::Withdrawal => 'Retrait',
            self::Commission => 'Commission',
        };
    }
}
