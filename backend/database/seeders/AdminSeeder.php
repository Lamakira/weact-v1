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
        Admin::updateOrCreate(
            ['email' => 'admin@weact.bj'],
            [
                'name' => 'Admin WEACT',
                'password' => 'password',
                'role' => AdminRole::SuperAdmin->value,
            ]
        );
    }
}
