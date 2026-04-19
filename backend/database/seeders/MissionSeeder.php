<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\Seeder;

class MissionSeeder extends Seeder
{
    /**
     * Seed development missions linked to the seeded Producer.
     */
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command?->warn(sprintf(
                'MissionSeeder skipped in %s environment — dev-only fixtures.',
                app()->environment()
            ));

            return;
        }

        $producerUser = User::where('email', 'abakar618@gmail.com')->first();

        if (! $producerUser || ! $producerUser->userable_id) {
            $this->command->warn('Producer user (abakar618@gmail.com) not found. Run ProducerSeeder first.');

            return;
        }

        $producerId = $producerUser->userable_id;

        Mission::firstOrCreate(
            ['titre' => 'Shooting photo marque streetwear'],
            [
                'producer_id' => $producerId,
                'description' => 'Recherche un visage pour une campagne photo d\'une marque de streetwear locale. Tournage en extérieur à Cotonou. Ambiance urbaine et décontractée.',
                'date_tournage' => now()->addDays(14)->toDateString(),
                'date_limite_candidature' => now()->addDays(7)->toDateString(),
                'profil_recherche' => 'Homme ou femme entre 20 et 30 ans, à l\'aise devant la caméra, style urbain.',
                'budget' => 50000,
                'nombre_faces_voulu' => 1,
                'type_mission' => MissionType::ShootingPhoto->value,
                'genre_voulu' => MissionGender::Tous->value,
                'lieu' => 'Cotonou, Bénin',
                'duree' => '1 journée',
                'status' => MissionStatus::Published->value,
            ]
        );

        Mission::firstOrCreate(
            ['titre' => 'Clip musical artiste afrobeats'],
            [
                'producer_id' => $producerId,
                'description' => 'Production d\'un clip musical pour un artiste afrobeats émergent. Besoin de 3 figurants/faces pour les scènes de danse et de lifestyle. Tournage en studio et en extérieur.',
                'date_tournage' => now()->addDays(21)->toDateString(),
                'date_limite_candidature' => now()->addDays(10)->toDateString(),
                'profil_recherche' => 'Femmes entre 18 et 28 ans, énergiques, à l\'aise avec la danse. Expérience en clip appréciée mais non obligatoire.',
                'budget' => 25000,
                'nombre_faces_voulu' => 3,
                'type_mission' => MissionType::ClipMusical->value,
                'genre_voulu' => MissionGender::Femme->value,
                'lieu' => 'Cotonou, Bénin',
                'duree' => '2 jours',
                'status' => MissionStatus::Published->value,
            ]
        );
    }
}
