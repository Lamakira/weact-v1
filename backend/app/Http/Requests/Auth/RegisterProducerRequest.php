<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterProducerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) config('app.registration_enabled', true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:agency,particulier'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
                'confirmed',
            ],
            'agency_name' => ['required_if:type,agency', 'nullable', 'string', 'max:255'],
            'first_name' => ['required_if:type,particulier', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_if:type,particulier', 'nullable', 'string', 'max:255'],
            'accept_cgu' => ['required', 'accepted'],
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
            'type.required' => 'Veuillez choisir un type de compte',
            'type.in' => 'Type de compte invalide',
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'L\'email doit être une adresse email valide',
            'email.unique' => 'Cet email est déjà utilisé',
            'password.required' => 'Le mot de passe est obligatoire',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas',
            'agency_name.required_if' => 'Le nom de l\'agence est obligatoire',
            'agency_name.max' => 'Le nom de l\'agence ne peut pas dépasser 255 caractères',
            'first_name.required_if' => 'Le prénom est obligatoire',
            'first_name.max' => 'Le prénom ne peut pas dépasser 255 caractères',
            'last_name.required_if' => 'Le nom est obligatoire',
            'last_name.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'accept_cgu.required' => 'Vous devez accepter les CGU et la Politique de Confidentialité.',
            'accept_cgu.accepted' => 'Vous devez accepter les CGU et la Politique de Confidentialité.',
        ];
    }

    /**
     * Handle a failed authorization attempt (registration disabled).
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'registration_disabled',
                    'message' => 'Les inscriptions sont temporairement suspendues. Veuillez réessayer ultérieurement.',
                ],
            ], 403)
        );
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
