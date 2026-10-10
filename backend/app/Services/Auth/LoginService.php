<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\PasswordTimingGuard;

/**
 * Service for handling user login
 */
class LoginService
{
    /**
     * Attempt to authenticate a user with email and password.
     *
     * @return array{user: User, token: string}|array{error: string}|null Returns user+token on success, error array if deactivated, null on bad credentials
     */
    public function login(string $email, string $password): ?array
    {
        // API auth is token-based; do not create a web session while checking credentials.
        $user = User::where('email', $email)->with('userable')->first();

        // An OAuth-only account has no password. Return the SAME generic failure as
        // bad credentials: a distinct code would be an account-enumeration oracle
        // ("this email exists and signs in with Google"), and the generic login
        // response is a property this codebase deliberately holds.
        //
        // PasswordTimingGuard always spends one bcrypt check (dummy hash when the
        // user is unknown or password-less) so response time does not reveal it.
        $passwordValid = PasswordTimingGuard::check($password, $user?->password);

        if ($user === null || ! $passwordValid) {
            return null;
        }

        // Check if account is deactivated
        if (! $user->is_active) {
            return ['error' => 'ACCOUNT_DEACTIVATED'];
        }

        // Generate Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
