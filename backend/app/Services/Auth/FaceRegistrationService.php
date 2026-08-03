<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Face;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FaceRegistrationService
{
    /**
     * Number of times the whole transaction is replayed when a concurrent signup
     * wins the race on the `faces.username` unique index.
     */
    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly UsernameGenerator $usernameGenerator) {}

    /**
     * Register a new Face user.
     *
     * @param  array{nom: string, prenom: string, email: string, password: string, date_naissance: string, accept_cgu?: bool}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    public function register(array $validated, ?string $ip = null): array
    {
        $result = $this->createAccount($validated, $ip);

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
     * Run the creation transaction, replaying it on a unique-index collision.
     *
     * The whole transaction is replayed (not just the insert) so a rolled-back
     * attempt never leaves a partial Face/User behind, whatever the driver.
     *
     * @param  array{nom: string, prenom: string, email: string, password: string, date_naissance: string, accept_cgu?: bool}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    private function createAccount(array $validated, ?string $ip): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $username = $attempt === 1
                ? $this->usernameGenerator->generate($validated['prenom'], $validated['nom'])
                : $this->usernameGenerator->generateWithRandomSuffix($validated['prenom'], $validated['nom']);

            try {
                return $this->persist($validated, $username, $ip);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        // Unreachable: the loop either returns or rethrows on the last attempt.
        throw new \LogicException('Face registration exhausted its retries without a result.');
    }

    /**
     * @param  array{nom: string, prenom: string, email: string, password: string, date_naissance: string, accept_cgu?: bool}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    private function persist(array $validated, string $username, ?string $ip): array
    {
        return DB::transaction(function () use ($validated, $username, $ip): array {
            // Create Face record first
            $face = Face::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'username' => $username,
                'date_naissance' => $validated['date_naissance'],
            ]);

            // Create User with polymorphic relationship to Face
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'userable_type' => Face::class,
                'userable_id' => $face->id,
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
                'face' => $face,
                'token' => $token,
            ];
        });
    }
}
