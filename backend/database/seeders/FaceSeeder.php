<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Face;
use App\Models\User;
use Illuminate\Database\Seeder;

class FaceSeeder extends Seeder
{
    /**
     * Seed development Face accounts with verified emails.
     */
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command?->warn(sprintf(
                'FaceSeeder skipped in %s environment — dev-only fixtures.',
                app()->environment()
            ));

            return;
        }

        $faces = [
            [
                'email' => 'amakira64dias@gmail.com',
                'password' => 'Azerty123',
                'nom' => 'Dias',
                'prenom' => 'Amakira',
                'username' => 'amakira64',
            ],
            [
                'email' => 'arikamachabis@gmail.com',
                'password' => 'Azerty123',
                'nom' => 'Machabis',
                'prenom' => 'Arikama',
                'username' => 'arikamachabis',
            ],
        ];

        foreach ($faces as $data) {
            $face = Face::firstOrCreate(
                ['username' => $data['username']],
                [
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                ]
            );

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'userable_type' => Face::class,
                    'userable_id' => $face->id,
                ]
            );
        }
    }
}
