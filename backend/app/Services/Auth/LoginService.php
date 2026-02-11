<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
        // Use Laravel's Auth::attempt() for standard authentication flow
        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return null;
        }

        // Get the authenticated user with userable relationship
        $user = User::where('email', $email)->with('userable')->firstOrFail();

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

