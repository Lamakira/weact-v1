<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\EscrowTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscrowTransaction>
 */
class EscrowTransactionFactory extends Factory
{
    protected $model = EscrowTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'amount' => fake()->numberBetween(10000, 100000),
            'status' => 'locked',
            'fedapay_ref' => null,
            'locked_at' => now(),
            'released_at' => null,
            'refunded_at' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }
}
