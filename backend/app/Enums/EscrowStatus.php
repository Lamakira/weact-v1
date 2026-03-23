<?php

declare(strict_types=1);

namespace App\Enums;

enum EscrowStatus: string
{
    case Pending = 'pending';
    case Locked = 'locked';
    case Released = 'released';
    case Refunded = 'refunded';
}
