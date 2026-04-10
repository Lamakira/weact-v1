<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'booking_id' => null,
            'type' => 'credit',
            'amount' => fake()->numberBetween(10000, 100000),
            'reference' => 'wlt_'.Str::uuid()->toString(),
            'description' => fake()->sentence(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'credit']);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'debit']);
    }
}
