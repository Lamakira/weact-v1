<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mission>
 */
class MissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Mission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dateTournage = fake()->dateTimeBetween('+1 week', '+3 months');
        $dateLimiteCandidature = fake()->dateTimeBetween('now', $dateTournage);

        return [
            'uuid' => fake()->uuid(),
            'producer_id' => Producer::factory(),
            'titre' => fake()->sentence(4),
            'description' => fake()->paragraphs(3, true),
            'date_tournage' => $dateTournage,
            'profil_recherche' => fake()->paragraph(),
            'budget' => fake()->numberBetween(50000, 500000), // XOF
            'date_limite_candidature' => $dateLimiteCandidature,
            'nombre_faces_voulu' => fake()->numberBetween(1, 10),
            'type_mission' => fake()->randomElement(MissionType::cases()),
            'genre_voulu' => fake()->randomElement(MissionGender::cases()),
            'lieu' => fake()->randomElement([
                'Cotonou',
                'Porto-Novo',
                'Parakou',
                'Djougou',
                'Bohicon',
                'Abomey-Calavi',
                'Kandi',
                'Lokossa',
                'Ouidah',
                'Natitingou',
            ]).', Bénin',
            'duree' => fake()->randomElement(['2 heures', '4 heures', '1 jour', '2 jours', '1 semaine']),
            'status' => MissionStatus::Draft,
        ];
    }

    /**
     * Indicate that the mission is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MissionStatus::Draft,
        ]);
    }

    /**
     * Indicate that the mission is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MissionStatus::Published,
        ]);
    }

    /**
     * Indicate that the mission is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MissionStatus::Closed,
        ]);
    }

    /**
     * Indicate that the mission is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MissionStatus::Completed,
        ]);
    }

    /**
     * Indicate that the mission type is "autre" with a custom label.
     */
    public function autre(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_mission' => MissionType::Autre,
            'type_mission_autre' => fake()->words(3, true),
        ]);
    }

    /**
     * Indicate that the mission is for a publicité.
     */
    public function publicite(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_mission' => MissionType::Publicite,
        ]);
    }

    /**
     * Indicate that the mission is for a film.
     */
    public function film(): static
    {
        return $this->state(fn (array $attributes) => [
            'type_mission' => MissionType::Film,
        ]);
    }

    /**
     * Indicate that the mission is for men only.
     */
    public function forMen(): static
    {
        return $this->state(fn (array $attributes) => [
            'genre_voulu' => MissionGender::Homme,
        ]);
    }

    /**
     * Indicate that the mission is for women only.
     */
    public function forWomen(): static
    {
        return $this->state(fn (array $attributes) => [
            'genre_voulu' => MissionGender::Femme,
        ]);
    }

    /**
     * Indicate that the mission is open to all genders.
     */
    public function forAll(): static
    {
        return $this->state(fn (array $attributes) => [
            'genre_voulu' => MissionGender::Tous,
        ]);
    }
}
