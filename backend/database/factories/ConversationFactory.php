<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Candidature;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Create an accepted candidature by default (chat access rule)
            'candidature_id' => Candidature::factory()->accepted(),
        ];
    }

    /**
     * Create conversation with a pending candidature (edge case testing).
     */
    public function withPendingCandidature(): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => Candidature::factory()->pending(),
        ]);
    }

    /**
     * Create conversation with a confirmed candidature.
     */
    public function withConfirmedCandidature(): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => Candidature::factory()->confirmed(),
        ]);
    }

    /**
     * Create conversation with an in-progress candidature.
     */
    public function withInProgressCandidature(): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => Candidature::factory()->inProgress(),
        ]);
    }

    /**
     * Create conversation with a completed candidature.
     */
    public function withCompletedCandidature(): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => Candidature::factory()->completed(),
        ]);
    }

    /**
     * Create conversation for a specific candidature.
     */
    public function forCandidature(Candidature $candidature): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => $candidature->id,
        ]);
    }
}
