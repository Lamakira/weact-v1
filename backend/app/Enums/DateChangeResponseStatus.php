<?php

declare(strict_types=1);

namespace App\Enums;

/** Réponse d'une Face (candidature) à un changement de date de mission. */
enum DateChangeResponseStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
