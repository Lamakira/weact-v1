<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AdminRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:admins,email'],
            // OWASP A07: the most privileged account type must meet the same password
            // policy as everyone else (>= 8 chars, at least one uppercase + one digit).
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d).+$/', 'confirmed'],
            'role' => ['sometimes', new Enum(AdminRole::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => "L'email est obligatoire.",
            'email.email' => "L'email doit être une adresse email valide.",
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'role' => 'Le rôle sélectionné est invalide.',
        ];
    }
}
