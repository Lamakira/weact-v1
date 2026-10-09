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
     * Version of the consent text shown to Producers (email and Google paths).
     * Unchanged since 2026-04-04: « J'accepte les CGU et la Politique de
     * Confidentialité » was not reworded when the Face label was.
     */
    public const CONSENT_VERSION = '2026-04-04';

    /**
     * Register a new Producer user.
     *
     * @param  array{type: string, email: string, password: string, agency_name?: string, nom_complet?: string, accept_cgu?: bool}  $validated
     * @return array{user: User, producer: Producer, token: string}
     */
    public function register(array $validated, ?string $ip = null): array
    {
        $result = $this->persist($validated, $ip, googleId: null);

        // Sent after the response, outside the transaction; a mail failure is logged
        // there and never fails the registration.
        $result['user']->sendEmailVerificationNotificationAfterResponse();

        return $result;
    }

    /**
     * Register a Producer whose identity comes from Google.
     *
     * No password and no verification mail — see FaceRegistrationService::registerFromGoogle.
     *
     * @param  array{type: string, email: string, agency_name?: string, nom_complet?: string}  $validated
     * @return array{user: User, producer: Producer, token: string}
     */
    public function registerFromGoogle(array $validated, string $googleId, ?string $ip = null): array
    {
        return $this->persist($validated, $ip, googleId: $googleId);
    }

    /**
     * @param  array{type: string, email: string, password?: string, agency_name?: string, nom_complet?: string, accept_cgu?: bool}  $validated
     * @return array{user: User, producer: Producer, token: string}
     */
    private function persist(array $validated, ?string $ip, ?string $googleId): array
    {
        return DB::transaction(function () use ($validated, $ip, $googleId): array {
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
                'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
                'userable_type' => Producer::class,
                'userable_id' => $producer->id,
                'consent_given_at' => now(),
                'consent_ip' => $ip,
                'consent_version' => self::CONSENT_VERSION,
            ]);

            if ($googleId !== null) {
                // Explicit assignment: google_id is deliberately not mass assignable.
                $user->google_id = $googleId;
                $user->google_linked_at = now();
                $user->email_verified_at = now();
                $user->save();
            }

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
