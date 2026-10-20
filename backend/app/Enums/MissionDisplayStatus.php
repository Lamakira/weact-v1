<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lot C3 : statut AFFICHÉ d'une mission. Dérivé du statut stocké, du type et de l'existence
 * d'un paiement payé (Mission::displayStatus) ; ne remplace jamais `MissionStatus`
 * (filtres, tri et gardes continuent de lire le statut stocké).
 */
enum MissionDisplayStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case PaymentInProgress = 'payment_in_progress';
    case CommissionDue = 'commission_due';
    case PaidPreparing = 'paid_preparing';
    case Closed = 'closed';
    case InProgress = 'in_progress';
    case AttendancePending = 'attendance_pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Published => 'Publiée',
            self::PaymentInProgress => 'Paiement en cours',
            self::CommissionDue => 'Commission à payer',
            self::PaidPreparing => 'Payée, en préparation',
            self::Closed => 'Clôturée',
            self::InProgress => 'En cours',
            self::AttendancePending => 'Présences à valider',
            self::Completed => 'Terminée',
        };
    }
}
