<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Face;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Face>
 */
class FaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Face::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'username' => fake()->unique()->userName(),
        ];
    }

    /**
     * Indicate that the face has a profile photo.
     */
    public function withProfilePhoto(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_photo' => fake()->uuid() . '.jpg',
            'profile_photo_thumbnail' => fake()->uuid() . '.jpg',
        ]);
    }
}
