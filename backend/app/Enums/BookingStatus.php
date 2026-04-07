<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Paid = 'paid';
    case InProgress = 'in_progress';
    case ConfirmedByFace = 'confirmed_by_face';
    case ConfirmedByProducer = 'confirmed_by_producer';
    case Completed = 'completed';
    case Expired = 'expired';
    case CancelledByProducer = 'cancelled_by_producer';
    case CancelledByFace = 'cancelled_by_face';
    case NoShow = 'no_show';

    /**
     * Get the display name in French for this booking status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptee',
            self::Refused => 'Refusee',
            self::Paid => 'Payee',
            self::InProgress => 'En cours',
            self::ConfirmedByFace => 'Confirmee par la Face',
            self::ConfirmedByProducer => 'Confirmee par le Producteur',
            self::Completed => 'Terminee',
            self::Expired => 'Expire',
            self::CancelledByProducer => 'Annulee par le Producteur',
            self::CancelledByFace => 'Annulee par la Face',
            self::NoShow => 'Absence signalee',
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
