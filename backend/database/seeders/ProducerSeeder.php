<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProducerType;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProducerSeeder extends Seeder
{
    /**
     * Seed the development Producer account with a verified email.
     */
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command?->warn(sprintf(
                'ProducerSeeder skipped in %s environment — dev-only fixtures.',
                app()->environment()
            ));

            return;
        }

        $producer = Producer::firstOrCreate(
            ['first_name' => 'Abakar', 'last_name' => 'Mahamat'],
            [
                'type' => ProducerType::Particulier->value,
            ]
        );

        User::updateOrCreate(
            ['email' => 'abakar618@gmail.com'],
            [
                'password' => 'Azerty123',
                'email_verified_at' => now(),
                'is_active' => true,
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
            ]
        );
    }
}
