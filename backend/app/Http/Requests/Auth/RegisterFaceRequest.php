<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\FaceGender;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterFaceRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:faces,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
                'confirmed',
            ],
            'sexe' => ['required', Rule::enum(FaceGender::class)],
            'date_naissance' => ['required', 'date', 'before_or_equal:'.now()->subYears(16)->format('Y-m-d')],
            'nationalite' => ['required', 'string', 'max:100'],
            'pays' => ['required', 'string', 'max:100'],
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
            'nom.required' => 'Le nom est obligatoire',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'prenom.required' => 'Le prénom est obligatoire',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères',
            'username.required' => 'Le nom d\'utilisateur est obligatoire',
            'username.max' => 'Le nom d\'utilisateur ne peut pas dépasser 50 caractères',
            'username.unique' => 'Ce nom d\'utilisateur est déjà pris',
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'L\'email doit être une adresse email valide',
            'email.unique' => 'Cet email est déjà utilisé',
            'password.required' => 'Le mot de passe est obligatoire',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas',
            'sexe.required' => 'Le sexe est obligatoire.',
            'sexe.enum' => 'Le sexe sélectionné est invalide.',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before_or_equal' => 'Vous devez avoir au moins 16 ans pour vous inscrire.',
            'nationalite.required' => 'La nationalité est obligatoire.',
            'nationalite.max' => 'La nationalité ne peut pas dépasser :max caractères.',
            'pays.required' => 'Le pays est obligatoire.',
            'pays.max' => 'Le pays ne peut pas dépasser :max caractères.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Les données fournies ne sont pas valides',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422)
        );
    }
}
