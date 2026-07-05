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
            'plan' => FaceSubscriptionPlan::Pro,
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'cancelled_at' => null,
            'reminder_30d_sent_at' => null,
            'reminder_7d_sent_at' => null,
            'paid_amount' => fake()->numberBetween(10000, 200000),
            'currency' => 'XOF',
            'provider' => null,
            'provider_reference' => null,
            'metadata' => null,
        ];
    }

    /**
     * Subscription awaiting payment confirmation.
     *
     * paid_amount/paid_at are reset to null to mirror production: initiate()
     * creates pending rows unpaid, and only markAsPaid() fills both. Without
     * the reset, the definition() default would leak phantom revenue into the
     * D-1 aggregates (SUM(paid_amount) regardless of status).
     */
    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::PendingPayment,
            'starts_at' => null,
            'expires_at' => null,
            'cancelled_at' => null,
            'paid_amount' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Active, unexpired subscription.
     *
     * Sets status + coverage window only — never `plan` — so the plan states
     * (starter/pro/elite) compose in either order, e.g. `elite()->active()`.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addYear(),
            'cancelled_at' => null,
        ]);
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
     *
     * paid_amount/paid_at reset to null: a failed row was never settled
     * (late approvals leave the row Failed with metadata only) — same
     * phantom-revenue guard as pendingPayment().
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FaceSubscriptionStatus::Failed,
            'starts_at' => null,
            'expires_at' => null,
            'cancelled_at' => null,
            'paid_amount' => null,
            'paid_at' => null,
        ]);
    }

    /**
     * Starter-tier subscription.
     */
    public function starter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan' => FaceSubscriptionPlan::Starter,
        ]);
    }

    /**
     * Pro-tier subscription.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan' => FaceSubscriptionPlan::Pro,
        ]);
    }

    /**
     * Élite-tier subscription.
     */
    public function elite(): static
    {
        return $this->state(fn (array $attributes): array => [
            'plan' => FaceSubscriptionPlan::Elite,
        ]);
    }
}
