<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\ProducerType;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProducerRegistrationService
{
    /**
     * Register a new Producer user.
     *
     * @param  array{type: string, email: string, password: string, agency_name?: string, nom_complet?: string, accept_cgu?: bool}  $validated
     * @return array{user: User, producer: Producer, token: string}
     */
    public function register(array $validated, ?string $ip = null): array
    {
        $result = DB::transaction(function () use ($validated, $ip): array {
            // Create Producer record first
            $producerData = [
                'type' => $validated['type'],
            ];

            // Add type-specific fields
            if ($validated['type'] === ProducerType::Agency->value) {
                $producerData['agency_name'] = $validated['agency_name'];
            } else {
                $producerData += $this->splitFullName($validated['nom_complet'] ?? '');
            }

            $producer = Producer::create($producerData);

            // Create User with polymorphic relationship to Producer
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
                'consent_given_at' => now(),
                'consent_ip' => $ip,
                'consent_version' => '2026-04-04',
            ]);

            // Generate Sanctum token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Load the userable relationship
            $user->load('userable');

            return [
                'user' => $user,
                'producer' => $producer,
                'token' => $token,
            ];
        });

        // Send email verification notification outside transaction
        // This ensures registration succeeds even if email fails
        try {
            $result['user']->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            // Log the error but don't fail registration
            \Log::warning('Failed to send verification email: '.$e->getMessage());
        }

        return $result;
    }

    /**
     * Split a single "nom complet" input into the two stored columns.
     *
     * The last whitespace-separated token becomes `last_name`, everything before it
     * `first_name` — so "Marie Ange Sossou" keeps its compound first name intact.
     * A single token leaves `last_name` empty; slugSourceName() trims the join, so
     * the public slug stays correct either way.
     *
     * @return array{first_name: string, last_name: string}
     */
    private function splitFullName(string $fullName): array
    {
        $tokens = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($tokens) < 2) {
            return [
                'first_name' => $tokens[0] ?? '',
                'last_name' => '',
            ];
        }

        $lastName = array_pop($tokens);

        return [
            'first_name' => implode(' ', $tokens),
            'last_name' => $lastName,
        ];
    }
}
