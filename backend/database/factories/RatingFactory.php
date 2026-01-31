<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Rating>
     */
    protected $model = Rating::class;

    /**
     * Define the model's default state.
     *
     * Creates a Face rating a Producer scenario by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Create Face with User
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        // Create Producer with User
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        // Create Mission for the Producer
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
        ]);

        // Create completed Candidature
        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);

        return [
            'candidature_id' => $candidature->id,
            'rater_id' => $faceUser->id,
            'rated_id' => $producer->id,
            'rated_type' => Producer::class,
            'score' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * State for Face rating a Producer.
     */
    public function faceRatingProducer(): static
    {
        return $this->state(function (array $attributes) {
            // Create Face with User
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            // Create Producer with User
            $producer = Producer::factory()->create();
            User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);

            // Create Mission for the Producer
            $mission = Mission::factory()->create([
                'producer_id' => $producer->id,
            ]);

            // Create completed Candidature
            $candidature = Candidature::factory()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
                'status' => CandidatureStatus::Completed,
            ]);

            return [
                'candidature_id' => $candidature->id,
                'rater_id' => $faceUser->id,
                'rated_id' => $producer->id,
                'rated_type' => Producer::class,
            ];
        });
    }

    /**
     * State for Producer rating a Face.
     */
    public function producerRatingFace(): static
    {
        return $this->state(function (array $attributes) {
            // Create Face with User
            $face = Face::factory()->create();
            User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);

            // Create Producer with User
            $producer = Producer::factory()->create();
            $producerUser = User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]);

            // Create Mission for the Producer
            $mission = Mission::factory()->create([
                'producer_id' => $producer->id,
            ]);

            // Create completed Candidature
            $candidature = Candidature::factory()->create([
                'face_id' => $face->id,
                'mission_id' => $mission->id,
                'status' => CandidatureStatus::Completed,
            ]);

            return [
                'candidature_id' => $candidature->id,
                'rater_id' => $producerUser->id,
                'rated_id' => $face->id,
                'rated_type' => Face::class,
            ];
        });
    }

    /**
     * State for a specific score.
     */
    public function score(int $score): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $score,
        ]);
    }

    /**
     * State for 1-star rating.
     */
    public function oneStar(): static
    {
        return $this->score(1);
    }

    /**
     * State for 2-star rating.
     */
    public function twoStars(): static
    {
        return $this->score(2);
    }

    /**
     * State for 3-star rating.
     */
    public function threeStars(): static
    {
        return $this->score(3);
    }

    /**
     * State for 4-star rating.
     */
    public function fourStars(): static
    {
        return $this->score(4);
    }

    /**
     * State for 5-star rating (perfect).
     */
    public function fiveStars(): static
    {
        return $this->score(5);
    }

    /**
     * State for rating with comment.
     */
    public function withComment(?string $comment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => $comment ?? $this->faker->paragraph(),
        ]);
    }

    /**
     * State for rating without comment.
     */
    public function withoutComment(): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => null,
        ]);
    }

    /**
     * State for using an existing candidature.
     */
    public function forCandidature(Candidature $candidature): static
    {
        return $this->state(fn (array $attributes) => [
            'candidature_id' => $candidature->id,
        ]);
    }

    /**
     * State for setting a specific rater.
     */
    public function ratedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'rater_id' => $user->id,
        ]);
    }

    /**
     * State for rating a specific Face.
     */
    public function ratingFace(Face $face): static
    {
        return $this->state(fn (array $attributes) => [
            'rated_id' => $face->id,
            'rated_type' => Face::class,
        ]);
    }

    /**
     * State for rating a specific Producer.
     */
    public function ratingProducer(Producer $producer): static
    {
        return $this->state(fn (array $attributes) => [
            'rated_id' => $producer->id,
            'rated_type' => Producer::class,
        ]);
    }
}
