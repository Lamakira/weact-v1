<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;

class MissionService
{
    /**
     * Create a new published mission for a Producer.
     *
     * @param  array<string, mixed>  $data
     */
    public function createMission(Producer $producer, array $data): Mission
    {
        return $producer->missions()->create([
            'titre' => $data['titre'],
            'description' => $data['description'],
            'date_tournage' => $data['date_tournage'],
            'profil_recherche' => $data['profil_recherche'],
            'budget' => $data['budget'],
            'date_limite_candidature' => $data['date_limite_candidature'],
            'nombre_faces_voulu' => $data['nombre_faces_voulu'] ?? 1,
            'type_mission' => $data['type_mission'],
            'genre_voulu' => $data['genre_voulu'],
            'lieu' => $data['lieu'],
            'duree' => $data['duree'],
            'status' => MissionStatus::Published,
        ]);
    }
}
