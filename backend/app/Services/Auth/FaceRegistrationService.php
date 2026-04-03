<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Face;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FaceRegistrationService
{
    /**
     * Register a new Face user.
     *
     * @param array{nom: string, prenom: string, username: string, email: string, password: string, sexe: string, date_naissance: string, nationalite: string, pays: string, whatsapp_number?: string|null} $validated
     * @return array{user: User, face: Face, token: string}
     */
    public function register(array $validated): array
    {
        $result = DB::transaction(function () use ($validated): array {
            // Create Face record first
            $face = Face::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'username' => $validated['username'],
                'sexe' => $validated['sexe'],
                'date_naissance' => $validated['date_naissance'],
                'nationalite' => $validated['nationalite'],
                'pays' => $validated['pays'],
                'whatsapp_number' => $validated['whatsapp_number'] ?? null,
            ]);

            // Create User with polymorphic relationship to Face
            $user = User::create([
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'userable_type' => Face::class,
                'userable_id' => $face->id,
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
}
