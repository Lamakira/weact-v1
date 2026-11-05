<?php

declare(strict_types=1);

return [
    // Autorisation d'utilisation de l'image (incrément 1 : bookings). Désactivé : comportement
    // strictement inchangé, API et écrans compris.
    'enabled' => (bool) env('IMAGE_CONTRACTS_ENABLED', false),
    // Durée de validité d'un code de signature, en minutes.
    'code_ttl_minutes' => 10,
    // Délai minimal entre deux envois de code (même utilisateur, même finalité), en secondes.
    'resend_delay_seconds' => 60,
    // Nombre de codes faux tolérés ; au-delà, le défi est invalidé.
    'max_attempts' => 5,
    // Disque privé du PDF signé.
    'storage_disk' => 'local',
];
