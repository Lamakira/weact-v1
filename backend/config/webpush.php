<?php

declare(strict_types=1);

/*
 * Overrides of laravel-notification-channels/webpush (the package merges its own
 * defaults for every other key: VAPID keys, table, ...).
 */
return [

    /**
     * Guzzle options for the push service calls: never follow redirects (SSRF
     * hardening on top of the endpoint host allowlist) and fail fast.
     */
    'client_options' => [
        'allow_redirects' => false,
        'timeout' => 10,
        'connect_timeout' => 5,
    ],

];
