<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsWhenRegistrationDisabled;
use Illuminate\Foundation\Http\FormRequest;

class RegisterProducerRequest extends FormRequest
{
    use RejectsWhenRegistrationDisabled;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) config('app.registration_enabled', true);
    }

    /**
     * // LEGACY-BUNDLE (deploy window): remove after the release following 2026-10
     *
     * SPA tabs opened before the deploy still post the old particulier form
     * (`first_name` + `last_name`, no `nom_complet`). Without this they would get a
     * 422 keyed on a field the old form cannot display: a silent failure.
     */
    protected function prepareForValidation(): void
    {
        $nomComplet = $this->input('nom_complet');
        $firstName = $this->input('first_name');

        if (
            $this->input('type') === 'particulier'
            && (! is_string($nomComplet) || trim($nomComplet) === '')
            && is_string($firstName)
            && trim($firstName) !== ''
        ) {
            $lastName = $this->input('last_name');

            $this->merge([
                'nom_complet' => trim($firstName.' '.(is_string($lastName) ? $lastName : '')),
            ]);
        }
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
            ],
            'agency_name' => ['required_if:type,agency', 'nullable', 'string', 'max:100'],
            // Collected as a single field and split server-side (see ProducerRegistrationService):
            // `first_name`/`last_name` only ever feed slugSourceName() and display_name, which
            // re-concatenate them, and the producer can fix the split from their profile.
            'nom_complet' => ['required_if:type,particulier', 'nullable', 'string', 'max:100'],
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
            'agency_name.required_if' => 'Le nom de l\'agence est obligatoire',
            'agency_name.max' => 'Le nom de l\'agence ne peut pas dépasser 100 caractères',
            'nom_complet.required_if' => 'Votre nom complet est obligatoire',
            'nom_complet.max' => 'Le nom complet ne peut pas dépasser 100 caractères',
            'accept_cgu.required' => 'Vous devez accepter les CGU et la Politique de Confidentialité.',
            'accept_cgu.accepted' => 'Vous devez accepter les CGU et la Politique de Confidentialité.',
        ];
    }
}
