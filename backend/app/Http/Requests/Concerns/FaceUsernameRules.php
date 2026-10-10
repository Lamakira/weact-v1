<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Face;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared rules for a Face's public `username` (the `/faces/{username}` URL segment).
 *
 * Consumers: Face\UpdateBasicInfoRequest and Admin\UpdateAdminFaceRequest — the
 * admin twin used to accept anything up to 50 characters, including `options`,
 * which shadows the public route `/faces/options`.
 *
 * The rules apply only when the username CHANGES: handles created before they
 * existed (`ab`, `Jean.Dupont`) are never re-validated nor rewritten when the Face
 * re-sends them unchanged.
 */
trait FaceUsernameRules
{
    /**
     * Minimum length of a username.
     */
    protected function usernameMinLength(): int
    {
        return UsernameGenerator::MIN_LENGTH;
    }

    /**
     * Trim and lowercase the submitted username, then drop it from the input when
     * it is the Face's current one — to be called from prepareForValidation().
     */
    protected function normalizeUsernameInput(?Face $face): void
    {
        if (! $this->has('username') || ! is_string($this->input('username'))) {
            return;
        }

        $username = Str::lower(trim($this->input('username')));

        if ($face !== null && $username !== '' && Str::lower((string) $face->username) === $username) {
            // Unchanged: nothing to validate, nothing to write.
            $this->offsetUnset('username');

            return;
        }

        $this->merge(['username' => $username]);
    }

    /**
     * @return list<mixed>
     */
    protected function usernameRules(?int $faceId): array
    {
        return [
            'sometimes',
            'required',
            'string',
            'min:'.$this->usernameMinLength(),
            'max:'.UsernameGenerator::MAX_LENGTH,
            'regex:/^[a-z0-9_-]+$/',
            Rule::notIn(UsernameGenerator::RESERVED),
            Rule::unique('faces', 'username')->ignore($faceId),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function usernameMessages(): array
    {
        return [
            'username.required' => "Le nom d'utilisateur est obligatoire",
            'username.min' => "Le nom d'utilisateur doit contenir au moins ".$this->usernameMinLength().' caractères',
            'username.max' => "Le nom d'utilisateur ne peut pas dépasser ".UsernameGenerator::MAX_LENGTH.' caractères',
            'username.regex' => "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores",
            'username.not_in' => "Ce nom d'utilisateur est réservé",
            'username.unique' => "Ce nom d'utilisateur est déjà pris",
        ];
    }
}
