<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Only AdminSeeder runs automatically via `php artisan db:seed`.
        // FaceSeeder / ProducerSeeder / MissionSeeder seed personal dev accounts
        // and must be invoked explicitly (e.g. `php artisan db:seed --class=FaceSeeder`)
        // to prevent accidental creation in shared environments.
        $this->call([
            AdminSeeder::class,
        ]);
    }
}
