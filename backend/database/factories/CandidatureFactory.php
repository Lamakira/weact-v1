<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidature>
 */
class CandidatureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Candidature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'face_id' => Face::factory(),
            'mission_id' => Mission::factory()->published(),
            'message_motivation' => fake()->optional(0.7)->paragraph(),
            'status' => CandidatureStatus::Pending,
        ];
    }

    /**
     * Indicate that the candidature is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::Pending,
        ]);
    }

    /**
     * Indicate that the candidature has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::Accepted,
        ]);
    }

    /**
     * Indicate that the candidature has been confirmed by the Face.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the candidature is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the candidature is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::Completed,
        ]);
    }

    /**
     * Indicate that the candidature has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CandidatureStatus::Rejected,
        ]);
    }

    /**
     * Indicate that the candidature has a motivation message.
     */
    public function withMotivation(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_motivation' => fake()->paragraphs(2, true),
        ]);
    }

    /**
     * Indicate that the candidature has no motivation message.
     */
    public function withoutMotivation(): static
    {
        return $this->state(fn (array $attributes) => [
            'message_motivation' => null,
        ]);
    }
}
