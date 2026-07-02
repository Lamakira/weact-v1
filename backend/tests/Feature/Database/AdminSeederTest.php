<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * OWASP A07 (H-1) — the AdminSeeder must never provision a trivial default
 * SuperAdmin (password `password`) in a shared environment (production/staging).
 */
class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_dev_admin_in_non_shared_environment(): void
    {
        // The test environment is non-shared → the convenience default is created.
        // Run the seeder directly (not via db:seed) so forcing APP_ENV=production
        // does not trigger the console "run in production?" confirmation prompt.
        $this->app->make(AdminSeeder::class)->run();

        $this->assertDatabaseHas('admins', [
            'email' => 'saidarikamachabi@gmail.com',
            'role' => AdminRole::SuperAdmin->value,
        ]);
    }

    public function test_skips_seeding_in_production_without_env_password(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Run the seeder directly (not via db:seed) so forcing APP_ENV=production
        // does not trigger the console "run in production?" confirmation prompt.
        $this->app->make(AdminSeeder::class)->run();

        $this->assertDatabaseCount('admins', 0);
    }

    public function test_rejects_weak_short_env_password_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->setEnv('ADMIN_SEED_PASSWORD', 'short'); // < 12 chars

        try {
            // Run the seeder directly (not via db:seed) so forcing APP_ENV=production
            // does not trigger the console "run in production?" confirmation prompt.
            $this->app->make(AdminSeeder::class)->run();
        } finally {
            $this->clearEnv('ADMIN_SEED_PASSWORD');
        }

        $this->assertDatabaseCount('admins', 0);
    }

    public function test_seeds_with_strong_env_password_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->setEnv('ADMIN_SEED_PASSWORD', 'Str0ngProdPassword!');
        $this->setEnv('ADMIN_SEED_EMAIL', 'prod-admin@weact.bj');

        try {
            // Run the seeder directly (not via db:seed) so forcing APP_ENV=production
            // does not trigger the console "run in production?" confirmation prompt.
            $this->app->make(AdminSeeder::class)->run();
        } finally {
            $this->clearEnv('ADMIN_SEED_PASSWORD');
            $this->clearEnv('ADMIN_SEED_EMAIL');
        }

        $admin = Admin::where('email', 'prod-admin@weact.bj')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('Str0ngProdPassword!', $admin->password));
        // The trivial default password is never valid in a shared environment.
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
