<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletCreditMotif: string
{
    case BookingCancellationRefund = 'booking_cancellation_refund';
    case BookingNoShowRefund = 'booking_no_show_refund';
    case UgcCommissionRefund = 'ugc_commission_refund';
    case UgcSettlementRefund = 'ugc_settlement_refund';
    case UgcSuspensionRefund = 'ugc_suspension_refund';

    public function label(): string
    {
        return match ($this) {
            self::BookingCancellationRefund => "Remboursement suite à l'annulation du booking",
            self::BookingNoShowRefund => "Remboursement suite à l'absence signalée de la Face",
            self::UgcCommissionRefund => 'Remboursement de la commission — deal UGC non abouti',
            self::UgcSettlementRefund => 'Remboursement du règlement UGC — deal non abouti',
            self::UgcSuspensionRefund => 'Remboursement suite à la suspension de la Face — deal UGC non livré',
        };
    }
}
