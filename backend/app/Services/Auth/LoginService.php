<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Service for handling user login
 */
class LoginService
{
    /**
     * Attempt to authenticate a user with email and password.
     *
     * @param string $email
     * @param string $password
     * @return array{user: User, token: string}|array{error: string}|null Returns user+token on success, error array if deactivated, null on bad credentials
     */
    public function login(string $email, string $password): ?array
    {
        // API auth is token-based; do not create a web session while checking credentials.
        $user = User::where('email', $email)->with('userable')->first();

        if ($user === null || !Hash::check($password, $user->password)) {
            return null;
        }

        // Check if account is deactivated
        if (!$user->is_active) {
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
