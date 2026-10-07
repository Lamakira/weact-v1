<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => AdminRole::Admin,
            // Par défaut un admin a la 2FA confirmée : la garde `admin.2fa` laisse passer.
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ];
    }

    /**
     * State: no 2FA enrolled (admin who must enrol at next login).
     */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * State: SuperAdmin role.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AdminRole::SuperAdmin,
        ]);
    }

    /**
     * State: Editor role.
     */
    public function editor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => AdminRole::Editor,
        ]);
    }
}
