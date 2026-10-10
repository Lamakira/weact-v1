<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Face;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FaceRegistrationService
{
    /**
     * Number of times the whole transaction is replayed when a concurrent signup
     * wins the race on the `faces.username` unique index.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Version of the consent text shown on the Face signup paths (email and Google).
     * Bumped to 2026-08-03 when the label became « J'ai 16 ans ou plus et j'accepte… ».
     */
    public const CONSENT_VERSION = '2026-08-03';

    public function __construct(private readonly UsernameGenerator $usernameGenerator) {}

    /**
     * Register a new Face user.
     *
     * @param  array{nom: string, prenom: string, email: string, password: string, date_naissance: string, accept_cgu?: bool, username?: string, sexe?: string|null, nationalite?: string|null, pays?: string|null, whatsapp_number?: string|null}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    public function register(array $validated, ?string $ip = null): array
    {
        $result = $this->createAccount($validated, $ip, googleId: null);

        // Sent after the response, outside the transaction; a mail failure is logged
        // there and never fails the registration.
        $result['user']->sendEmailVerificationNotificationAfterResponse();

        return $result;
    }

    /**
     * Register a Face whose identity comes from Google.
     *
     * No password (the column is nullable) and no verification mail: Google has
     * already attested the address, so `email_verified_at` is set here and mailing
     * a link would only add friction — and would leave the account blocked on the
     * routes gated by the `verified` middleware for nothing.
     *
     * @param  array{nom: string, prenom: string, email: string, date_naissance: string}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    public function registerFromGoogle(array $validated, string $googleId, ?string $ip = null): array
    {
        return $this->createAccount($validated, $ip, googleId: $googleId);
    }

    /**
     * Run the creation transaction, replaying it on a unique-index collision.
     *
     * The whole transaction is replayed (not just the insert) so a rolled-back
     * attempt never leaves a partial Face/User behind, whatever the driver.
     *
     * @param  array{nom: string, prenom: string, email: string, password?: string, date_naissance: string, accept_cgu?: bool, username?: string, sexe?: string|null, nationalite?: string|null, pays?: string|null, whatsapp_number?: string|null}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    private function createAccount(array $validated, ?string $ip, ?string $googleId): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            // // LEGACY-BUNDLE (deploy window): remove after the release following 2026-10
            // A handle chosen on the old signup form already passed validation
            // (incl. uniqueness): use it as-is on the first attempt. Should a concurrent
            // signup take it meanwhile, the replays fall back to generated handles.
            $submitted = $validated['username'] ?? null;

            if ($attempt === 1 && is_string($submitted) && $submitted !== '') {
                $username = $submitted;
            } elseif ($attempt === 1) {
                $username = $this->usernameGenerator->generate($validated['prenom'], $validated['nom']);
            } else {
                $username = $this->usernameGenerator->generateWithRandomSuffix($validated['prenom'], $validated['nom']);
            }

            try {
                return $this->persist($validated, $username, $ip, $googleId);
            } catch (UniqueConstraintViolationException $e) {
                // Only a username collision is worth replaying. If no Face holds that
                // username, the violation was on another column (users.email,
                // users.google_id): a retry can only fail again, so surface it now.
                if ($attempt === self::MAX_ATTEMPTS || Face::query()->where('username', $username)->doesntExist()) {
                    throw $e;
                }
            }
        }

        // Unreachable: the loop either returns or rethrows on the last attempt.
        throw new \LogicException('Face registration exhausted its retries without a result.');
    }

    /**
     * // LEGACY-BUNDLE (deploy window): remove after the release following 2026-10
     *
     * Profile fields the old signup form still posts; only the ones actually
     * submitted are written, so the column defaults apply otherwise.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function legacyBundleProfileFields(array $validated): array
    {
        return array_filter(
            Arr::only($validated, ['sexe', 'nationalite', 'pays', 'whatsapp_number']),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param  array{nom: string, prenom: string, email: string, password?: string, date_naissance: string, accept_cgu?: bool, username?: string, sexe?: string|null, nationalite?: string|null, pays?: string|null, whatsapp_number?: string|null}  $validated
     * @return array{user: User, face: Face, token: string}
     */
    private function persist(array $validated, string $username, ?string $ip, ?string $googleId): array
    {
        return DB::transaction(function () use ($validated, $username, $ip, $googleId): array {
            // Create Face record first
            $face = Face::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'username' => $username,
                'date_naissance' => $validated['date_naissance'],
            ] + $this->legacyBundleProfileFields($validated));

            // Create User with polymorphic relationship to Face
            $user = User::create([
                'email' => $validated['email'],
                'password' => isset($validated['password']) ? Hash::make($validated['password']) : null,
                'userable_type' => Face::class,
                'userable_id' => $face->id,
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
                'face' => $face,
                'token' => $token,
            ];
        });
    }
}
