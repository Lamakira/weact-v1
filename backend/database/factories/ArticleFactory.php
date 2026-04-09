<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'admin_id' => Admin::factory(),
            'title' => fake()->sentence(6),
            'content' => fake()->paragraphs(5, true),
            'excerpt' => fake()->paragraph(),
            'category' => fake()->randomElement(ArticleCategory::values()),
            'status' => ArticleStatus::Draft->value,
            'featured_image' => null,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the article is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the article is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    /**
     * Set the article category.
     */
    public function inCategory(ArticleCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category->value,
        ]);
    }
}
