<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deliverable>
 *
 * Owner polymorphe NON posé en définition (calque manuel morph) : rattacher via
 * `$owner->deliverables()->save(Deliverable::factory()->make())` ou
 * `Deliverable::factory()->for($owner, 'owner')->create()`.
 */
class DeliverableFactory extends Factory
{
    protected $model = Deliverable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'kind' => DeliverableKind::Unboxing,
            'validation_status' => DeliverableValidationStatus::InReview,
            'chrono_started_at' => now()->subDay(),
            'deadline_at' => now()->addDays(6),
            'video_path' => 'ugc/deliverables/unboxing/'.fake()->uuid().'.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/'.fake()->uuid().'.jpg',
            'duree_seconds' => 42,
            'submitted_at' => now()->subHours(2),
        ];
    }

    public function unboxing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => DeliverableKind::Unboxing,
            'video_path' => 'ugc/deliverables/unboxing/'.fake()->uuid().'.mp4',
            'thumbnail_path' => 'ugc/deliverables/unboxing/thumbnails/'.fake()->uuid().'.jpg',
        ]);
    }

    public function avis(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => DeliverableKind::Avis,
            'video_path' => 'ugc/deliverables/avis/'.fake()->uuid().'.mp4',
            'thumbnail_path' => 'ugc/deliverables/avis/thumbnails/'.fake()->uuid().'.jpg',
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => DeliverableValidationStatus::Validated,
            'validated_at' => now()->subHour(),
            'review_note' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => DeliverableValidationStatus::Rejected,
            'validated_at' => null,
            'review_note' => 'À refaire : cadrage hors sujet.',
        ]);
    }

    public function retoucheRequested(): static
    {
        return $this->state(fn (array $attributes): array => [
            'validation_status' => DeliverableValidationStatus::RetoucheRequested,
            'validated_at' => null,
            'review_note' => 'Ajoute le plan packaging.',
        ]);
    }
}
