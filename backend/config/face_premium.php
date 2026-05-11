<?php

declare(strict_types=1);

return [
    'annual_plan' => [
        'amount' => (int) env('FACE_PREMIUM_ANNUAL_AMOUNT', 50000),
        'currency' => env('FACE_PREMIUM_ANNUAL_CURRENCY', 'XOF'),
        'provider' => env('FACE_PREMIUM_ANNUAL_PROVIDER', 'fedapay'),
    ],
];
