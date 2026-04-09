<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(5000, 80000),
            'payment_mode' => fake()->randomElement(['mtn', 'moov', 'momo_test']),
            'phone_number' => fake()->numerify('6#######'),
            'phone_country' => fake()->randomElement(['bj', 'tg', 'ci', 'sn', 'bf']),
            'status' => 'pending',
            'notes' => null,
            'wallet_transaction_id' => null,
            'processed_by_admin_id' => null,
            'processed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'processed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'notes' => 'Informations insuffisantes.',
            'processed_at' => now(),
        ]);
    }
}
