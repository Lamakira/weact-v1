<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BookingMessage;
use App\Models\Message;

/**
 * Message « position » des discussions : position instantanée, jamais de suivi en direct.
 *
 * Les coordonnées vivent dans des colonnes dédiées ; `content` reçoit un texte neutre pour que
 * aperçus, push, e-mails et WhatsApp (qui lisent `content`) ne divulguent jamais de coordonnées.
 */
final class MessageLocation
{
    public const CONTENT = 'Position partagée';

    /**
     * Colonnes à écrire (et `content`) pour un message position. Tableau vide si pas de position.
     *
     * Les coordonnées sont arrondies à 6 décimales (précision des colonnes) pour que la réponse
     * immédiate et la relecture ultérieure portent exactement les mêmes valeurs.
     *
     * @param  array<string, mixed>|null  $location
     * @return array<string, mixed>
     */
    public static function attributes(?array $location): array
    {
        if ($location === null) {
            return [];
        }

        return [
            'content' => self::CONTENT,
            'location_lat' => round((float) $location['lat'], 6),
            'location_lng' => round((float) $location['lng'], 6),
            'location_accuracy_m' => isset($location['accuracy_m']) ? (int) $location['accuracy_m'] : null,
        ];
    }

    /**
     * Clé `location` des réponses API et du temps réel : null ou { lat, lng, accuracy_m, maps_url }.
     *
     * @return array{lat: float, lng: float, accuracy_m: int|null, maps_url: string}|null
     */
    public static function payload(Message|BookingMessage $message): ?array
    {
        if ($message->location_lat === null || $message->location_lng === null) {
            return null;
        }

        $lat = (float) $message->location_lat;
        $lng = (float) $message->location_lng;

        return [
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $message->location_accuracy_m,
            'maps_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
        ];
    }
}
