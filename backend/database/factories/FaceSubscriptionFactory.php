<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaceSubscription>
 */
class FaceSubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = FaceSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();
        $expiresAt = (clone $startsAt)->addYear();

        return [
            'uuid' => fake()->uuid(),
            'face_id' => Face::factory(),
            'plan' => FaceSubscriptionPlan::AnnualPremium,
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'cancelled_at' => null,
            'paid_amount' => fake()->numberBetween(10000, 200000),
            'currency' => 'XOF',
            'provider' => null,
            'provider_reference' => null,
            'metadata' => null,
        ];
    }

    /**
     * Subscription awaiting payment confirmation.
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::PendingPayment,
            'starts_at' => null,
            'expires_at' => null,
            'cancelled_at' => null,
        ]);
    }

    /**
     * Active, unexpired annual premium subscription.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes): array {
            $startsAt = now()->subDays(7);
            $expiresAt = now()->addYear();

            return [
                'status' => FaceSubscriptionStatus::Active,
                'plan' => FaceSubscriptionPlan::AnnualPremium,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'cancelled_at' => null,
            ];
        });
    }

    /**
     * Subscription whose paid coverage window is over.
     */
    public function expired(): static
    {
        return $this->state(function (array $attributes): array {
            $startsAt = now()->subYear()->subDay();
            $expiresAt = now()->subDay();

            return [
                'status' => FaceSubscriptionStatus::Expired,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'cancelled_at' => null,
            ];
        });
    }

    /**
     * Subscription cancelled by Face or admin.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Subscription whose payment attempt failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::Failed,
            'starts_at' => null,
            'expires_at' => null,
            'cancelled_at' => null,
        ]);
    }
}
