<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProducerType;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Producer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isAgency = fake()->boolean(30);

        return [
            'uuid' => fake()->uuid(),
            'slug' => fake()->unique()->slug(3),
            'type' => $isAgency ? ProducerType::Agency : ProducerType::Particulier,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'agency_name' => $isAgency ? fake()->company() : null,
        ];
    }

    /**
     * Indicate that the producer is an agency.
     */
    public function agency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProducerType::Agency,
            'agency_name' => fake()->company(),
        ]);
    }

    /**
     * Indicate that the producer is an individual.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProducerType::Particulier,
            'agency_name' => null,
        ]);
    }
}
