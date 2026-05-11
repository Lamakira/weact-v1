<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceSubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    /**
     * Get the display name in French for this subscription status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::Active => 'Active',
            self::Expired => 'Expirée',
            self::Cancelled => 'Annulée',
            self::Failed => 'Échouée',
        };
    }

    /**
     * Get all enum values as an array of strings.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
