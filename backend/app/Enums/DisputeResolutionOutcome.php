<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeResolutionOutcome: string
{
    case FavorFace = 'favor_face';
    case FavorProducer = 'favor_producer';
}
