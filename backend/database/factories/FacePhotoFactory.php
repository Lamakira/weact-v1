<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Face;
use App\Models\FacePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FacePhoto>
 */
class FacePhotoFactory extends Factory
{
    protected $model = FacePhoto::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::uuid()->toString().'.jpg';

        return [
            'uuid' => fake()->uuid(),
            'face_id' => Face::factory(),
            'filename' => $filename,
            'thumbnail' => $filename,
            'position' => 1, // Default to 1, use sequence() or explicit position for multiple photos
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (FacePhoto $photo) {
            // If face_id is set and position is still default, calculate next position
            if ($photo->face_id && $photo->position === 1) {
                $existingCount = FacePhoto::where('face_id', $photo->face_id)->count();
                if ($existingCount > 0) {
                    $photo->position = $existingCount + 1;
                }
            }
        });
    }

    /**
     * Indicate that the photo is at a specific position.
     */
    public function position(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Create multiple photos with sequential positions for a face.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FacePhoto>
     */
    public static function createSequentialForFace(Face $face, int $count): \Illuminate\Database\Eloquent\Collection
    {
        $photos = collect();
        for ($i = 1; $i <= $count; $i++) {
            $photos->push(
                FacePhoto::factory()->create([
                    'face_id' => $face->id,
                    'position' => $i,
                ])
            );
        }

        return new \Illuminate\Database\Eloquent\Collection($photos->all());
    }
}
