<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ChangeEmailRequest extends FormRequest
{
    /**
     * The email is the takeover surface: change the email, then reset the password,
     * and you own the account. An OAuth-only user has no password to re-authenticate
     * with, so this stays closed until they set one — one click away, and unlike
     * erasure there is no legal duty to make it frictionless.
     */
    public function authorize(): bool
    {
        return $this->user()?->password !== null;
    }

    /**
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'EMAIL_CHANGE_REQUIRES_PASSWORD',
                    'message' => 'Définissez d\'abord un mot de passe pour pouvoir changer votre adresse email.',
                ],
            ], 403)
        );
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'password' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user && $this->filled('email') && $this->input('email') === $user->email) {
                $validator->errors()->add('email', 'La nouvelle adresse email doit être différente de l\'actuelle.');
            }

            if ($user && $this->filled('password') && ! Hash::check($this->input('password'), $user->password)) {
                $validator->errors()->add('password', 'Le mot de passe est incorrect.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => "L'email est obligatoire.",
            'email.email' => "L'email doit être une adresse email valide.",
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }
}
