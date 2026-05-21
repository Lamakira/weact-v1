<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FaceSubscriptionAdminAction;
use App\Models\Admin;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaceSubscriptionAudit>
 */
class FaceSubscriptionAuditFactory extends Factory
{
    protected $model = FaceSubscriptionAudit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'face_subscription_id' => FaceSubscription::factory(),
            'admin_id' => Admin::factory(),
            'action' => FaceSubscriptionAdminAction::ManualActivate,
            'notes' => fake()->sentence(8),
            'previous_state' => null,
            'new_state' => [
                'plan' => 'pro',
                'tier' => 'pro',
                'status' => 'active',
                'starts_at' => now()->toIso8601String(),
                'expires_at' => now()->addYear()->toIso8601String(),
                'cancelled_at' => null,
                'paid_amount' => null,
                'currency' => 'XOF',
            ],
        ];
    }
}
