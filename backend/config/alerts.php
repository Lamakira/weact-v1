<?php

declare(strict_types=1);

/**
 * Alertes e-mail admin sur les logs critiques (critical / alert / emergency).
 * Désactivé tant que ADMIN_ALERT_EMAILS est vide ou ne contient aucune adresse valide.
 */
$recipients = array_values(array_unique(array_filter(
    array_map('trim', explode(',', (string) env('ADMIN_ALERT_EMAILS', ''))),
    fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
)));

return [
    'critical' => [
        'recipients' => $recipients,
        // Un même événement (niveau + message + contexte) n'alerte qu'une fois sur cette fenêtre.
        'dedup_minutes' => 30,
        // Plafond global d'e-mails d'alerte par heure (anti-inondation).
        'max_per_hour' => 20,
    ],
];
