<?php

/*
|--------------------------------------------------------------------------
| Alertes WhatsApp (WhatsApp Cloud API de Meta, en direct)
|--------------------------------------------------------------------------
|
| Fonctionnalité disponible = `enabled` ET `phone_number_id` ET `access_token` non vides.
| Indisponible : aucun envoi, aucun job, routes /me/whatsapp-alerts en 404.
|
| Les modèles sont de catégorie UTILITY, en français, vouvoiement, sans texte de message échangé.
| `php artisan whatsapp:sync-templates` crée chez Meta ceux qui manquent.
| Le bouton URL pointe vers `{public_url}/{{1}}` : le suffixe dynamique {{1}} est le chemin de la
| page (sans « / » initial) ; `url_example` sert d'exemple à Meta à la création du modèle.
|
*/

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    'app_secret' => env('WHATSAPP_APP_SECRET'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    // Base des liens des boutons ; par défaut l'URL du frontend.
    'public_url' => env('WHATSAPP_PUBLIC_URL') ?: env('FRONTEND_URL', 'http://localhost:5173'),

    'language' => 'fr',

    // Délai HTTP (secondes) : l'envoi du message non lu est synchrone.
    'timeout' => 10,

    // Au plus un message « nouvelles candidatures » par mission et par Producteur sur cette fenêtre.
    'candidatures_window_hours' => 6,

    'templates' => [
        'weact_booking_request' => [
            'body' => 'Bonjour {{1}}, {{2}} vous propose un booking le {{3}}. Vous avez jusqu\'au {{4}} pour répondre sur WeAct.',
            'button' => 'Voir la demande',
            'examples' => ['Awa', 'Studio Kora', 'lundi 27 octobre', 'samedi 25 octobre à 16 h'],
            'url_example' => 'face/bookings/00000000-0000-0000-0000-000000000000',
        ],
        'weact_booking_reminder' => [
            'body' => 'Bonjour {{1}}, la demande de booking de {{2}} expire le {{3}}. Répondez sur WeAct pour ne pas la perdre.',
            'button' => 'Répondre',
            'examples' => ['Awa', 'Studio Kora', 'samedi 25 octobre à 16 h'],
            'url_example' => 'face/bookings/00000000-0000-0000-0000-000000000000',
        ],
        'weact_payment_secured' => [
            'body' => 'Bonjour {{1}}, le paiement de {{2}} pour « {{3}} » est sécurisé. La discussion est ouverte sur WeAct.',
            'button' => 'Voir le booking',
            'examples' => ['Awa', 'Studio Kora', 'Clip musical'],
            'url_example' => 'face/bookings/00000000-0000-0000-0000-000000000000',
        ],
        'weact_mission_place_paid' => [
            'body' => 'Bonjour {{1}}, {{2}} a retenu et payé votre place pour « {{3}} ». Confirmez votre participation sur WeAct.',
            'button' => 'Voir la mission',
            'examples' => ['Awa', 'Studio Kora', 'Lookbook Wax'],
            'url_example' => 'face/missions/00000000-0000-0000-0000-000000000000',
        ],
        'weact_booking_accepted' => [
            'body' => 'Bonjour {{1}}, {{2}} a accepté votre demande de booking du {{3}}. Vous pouvez payer pour ouvrir la discussion.',
            'button' => 'Voir le booking',
            'examples' => ['Studio', 'Awa', 'lundi 27 octobre'],
            'url_example' => 'producer/bookings/00000000-0000-0000-0000-000000000000',
        ],
        'weact_booking_declined' => [
            'body' => 'Bonjour {{1}}, {{2}} a décliné votre demande de booking du {{3}}.',
            'button' => 'Voir le booking',
            'examples' => ['Studio', 'Awa', 'lundi 27 octobre'],
            'url_example' => 'producer/bookings/00000000-0000-0000-0000-000000000000',
        ],
        'weact_new_candidatures' => [
            'body' => 'Bonjour {{1}}, votre mission « {{2}} » a reçu {{3}}. Consultez les candidatures sur WeAct.',
            'button' => 'Voir les candidatures',
            'examples' => ['Studio', 'Lookbook Wax', '3 nouvelles candidatures'],
            'url_example' => 'producer/missions/00000000-0000-0000-0000-000000000000?tab=candidatures',
        ],
        'weact_unread_message' => [
            'body' => 'Bonjour {{1}}, {{2}} vous a écrit sur WeAct à propos de « {{3}} ». Répondez depuis votre messagerie WeAct.',
            'button' => 'Ouvrir la discussion',
            'examples' => ['Awa', 'Studio Kora', 'Lookbook Wax'],
            'url_example' => 'face/conversations/00000000-0000-0000-0000-000000000000',
        ],
    ],
];
