<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Experience;
use App\Models\Face;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    protected $model = Experience::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'face_id' => Face::factory(),
            'titre' => fake()->sentence(3),
            'description' => fake()->optional(0.7)->paragraph(),
            'annee' => fake()->numberBetween(2015, (int) date('Y')),
        ];
    }

    /**
     * Create an experience for a specific year.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes): array => [
            'annee' => $year,
        ]);
    }

    /**
     * Create an experience without description.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => null,
        ]);
    }

    /**
     * Create an experience with a specific title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes): array => [
            'titre' => $title,
        ]);
    }
}
