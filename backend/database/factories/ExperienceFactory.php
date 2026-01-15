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
        $startDate = fake()->dateTimeBetween('-5 years', '-1 month');
        $endDate = fake()->optional(0.7)->dateTimeBetween($startDate, 'now');

        return [
            'face_id' => Face::factory(),
            'titre' => fake()->sentence(3),
            'description' => fake()->optional(0.7)->paragraph(),
            'date_debut' => $startDate->format('Y-m-d'),
            'date_fin' => $endDate?->format('Y-m-d'),
        ];
    }

    /**
     * Create an experience starting on a specific date.
     */
    public function startingOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_debut' => $date,
        ]);
    }

    /**
     * Create an experience ending on a specific date.
     */
    public function endingOn(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_fin' => $date,
        ]);
    }

    /**
     * Create an ongoing experience (null date_fin).
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_fin' => null,
        ]);
    }

    /**
     * Create an experience with a complete date range.
     */
    public function withDateRange(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_debut' => $startDate,
            'date_fin' => $endDate,
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
