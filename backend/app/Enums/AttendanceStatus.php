<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Pending = 'pending';
    case Present = 'present';
    case Absent = 'absent';
    case Disputed = 'disputed';
}
