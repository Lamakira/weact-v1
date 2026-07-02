<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default admin account for development.
     */
    public function run(): void
    {
        // OWASP A07 hardening: never seed a trivial default SuperAdmin in shared
        // environments. Production/staging MUST provide an explicit strong password
        // via ADMIN_SEED_PASSWORD (>= 12 chars); otherwise the seeder is skipped and
        // the first admin must be provisioned manually (e.g. `php artisan tinker`).
        // In local/dev a convenience default is kept so the dev/test bootstrap is unchanged.
        $isShared = app()->environment('production', 'staging');
        $password = (string) env('ADMIN_SEED_PASSWORD', '');

        if ($isShared && strlen($password) < 12) {
            $this->command?->warn(
                'AdminSeeder skipped: set ADMIN_SEED_PASSWORD (>= 12 chars) to seed a SuperAdmin in this environment.'
            );

            return;
        }

        Admin::updateOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'saidarikamachabi@gmail.com')],
            [
                'name' => 'Admin WEACT',
                'password' => $password !== '' ? $password : 'password', // dev-only fallback (non-shared env)
                'role' => AdminRole::SuperAdmin->value,
            ]
        );
    }
}
