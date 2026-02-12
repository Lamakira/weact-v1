<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    /**
     * Update the authenticated user's password.
     */
    public function update(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('new_password')),
        ]);

        $user->notify(new PasswordChangedNotification());

        return response()->json([
            'data' => ['password_changed' => true],
            'meta' => [],
            'message' => 'Votre mot de passe a été modifié avec succès.',
        ]);
    }
}
