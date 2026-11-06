<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statut d'une demande de changement de date.
 * Mission : pending puis completed (toutes les réponses résolues).
 * Booking : pending puis accepted, refused, expired ou withdrawn.
 */
enum DateChangeStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
}
