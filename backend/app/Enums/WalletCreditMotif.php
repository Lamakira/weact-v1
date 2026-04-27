<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletCreditMotif: string
{
    case BookingCancellationRefund = 'booking_cancellation_refund';
    case BookingNoShowRefund = 'booking_no_show_refund';

    public function label(): string
    {
        return match ($this) {
            self::BookingCancellationRefund => "Remboursement suite à l'annulation du booking",
            self::BookingNoShowRefund => "Remboursement suite à l'absence signalée de la Face",
        };
    }
}
