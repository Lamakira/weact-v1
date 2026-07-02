<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\FaceSubscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FaceSubscriptionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FaceSubscription $subscription,
    ) {}
}
