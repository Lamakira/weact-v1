<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

/**
 * Service for handling admin login
 */
class AdminLoginService
{
    /**
     * Attempt to authenticate an admin with email and password.
     *
     * @return array{admin: Admin, token: string}|null Returns admin and token on success, null on failure
     */
    public function login(string $email, string $password): ?array
    {
        $admin = Admin::where('email', $email)->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            return null;
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return [
            'admin' => $admin,
            'token' => $token,
        ];
    }
}
