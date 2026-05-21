<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FaceVideoType;
use App\Models\Face;
use App\Models\FaceVideo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FaceVideo>
 */
class FaceVideoFactory extends Factory
{
    protected $model = FaceVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'face_id' => Face::factory(),
            'type' => FaceVideoType::Acting,
            'filename' => Str::uuid()->toString().'.mp4',
            'thumbnail' => Str::uuid()->toString().'.jpg',
            'position' => 1,
        ];
    }

    public function acting(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => FaceVideoType::Acting,
        ]);
    }

    public function ugc(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => FaceVideoType::Ugc,
        ]);
    }

    public function position(int $position): static
    {
        return $this->state(fn (array $attributes): array => [
            'position' => $position,
        ]);
    }

    /**
     * Create $count videos of one type with sequential positions for a Face.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FaceVideo>
     */
    public static function createSequentialForFace(Face $face, FaceVideoType $type, int $count): Collection
    {
        $videos = [];

        for ($i = 1; $i <= $count; $i++) {
            $videos[] = FaceVideo::factory()->create([
                'face_id' => $face->id,
                'type' => $type,
                'position' => $i,
            ]);
        }

        return new Collection($videos);
    }
}
